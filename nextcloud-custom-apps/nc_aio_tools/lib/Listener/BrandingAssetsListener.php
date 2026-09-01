<?php

declare(strict_types=1);

namespace OCA\NcAioTools\Listener;

use OCA\NcAioTools\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent|BeforeLoginTemplateRenderedEvent>
 *
 * Loads the BharatSuite theme layer on every rendered page.
 *
 * TWO events, not one. BeforeTemplateRenderedEvent covers the app pages but does NOT
 * fire for the login screen -- that goes through the guest template, dispatched
 * separately as BeforeLoginTemplateRenderedEvent by AdditionalScriptsMiddleware.
 * Registering only the first leaves the login page as stock Nextcloud, which is how
 * this was originally missed.
 *
 * The stylesheet only reassigns Nextcloud's own CSS variables, so load order against
 * core and app stylesheets does not matter -- the variables are resolved at use time,
 * not at parse time.
 */
class BrandingAssetsListener implements IEventListener {
	/**
	 * Set on the nextcloud container in docker-compose.yml. Blank or unset means the
	 * operator is making no residency claim, and no badge is rendered.
	 */
	private const ENV_RESIDENCY_LABEL = 'BRANDING_RESIDENCY_LABEL';

	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent
			&& !$event instanceof BeforeLoginTemplateRenderedEvent) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'bharatsuite');

		// Brand marks have to be emitted here rather than declared in the stylesheet.
		// A relative url() inside a custom property resolves against the stylesheet
		// that USES the variable -- core/css/guest.css -- not the one that declares
		// it, so '../img/logo.svg' silently became '/core/img/logo.svg'. Absolute
		// URLs from IURLGenerator are unambiguous and survive a subdirectory install.
		//
		// TWO variants, one per ground.
		//
		//   logo.svg       Two-tone, for the paper ground: blue "Bharat" (#1c4478),
		//                  ink "Suite", saffron slash. The login screen, the light
		//                  page, and the light header bar.
		//   logo-dark.svg  "Suite" in paper and "Bharat" a lighter blue, for the dark
		//                  theme's ink ground and its lifted header bar.
		//
		// There used to be a third, logo-header.svg, with both words in white. It
		// existed only because the header bar was deep blue: "Bharat" in logo.svg was
		// #163f85 and so was the bar, so the light variant lost half the wordmark
		// into the fill. The bar is paper again in the shipped mockup track, so the
		// wordmark on it is the ordinary light mark and the third file is gone.
		//
		// --image-logoheader still has to be set alongside --image-logo rather than
		// left to default: core falls back to its own white mark, not to --image-logo.
		// The two tokens are separate precisely so the grounds can differ; here they
		// simply agree.
		$light = $this->urlGenerator->linkTo(Application::APP_ID, 'img/logo.svg');
		$dark  = $this->urlGenerator->linkTo(Application::APP_ID, 'img/logo-dark.svg');

		Util::addHeader('style', ['type' => 'text/css'], sprintf(
			':root:root{--image-logo:url(%1$s);--image-logoheader:url(%1$s)}'
			. 'body[data-theme-dark],body[data-theme-dark-highcontrast]'
			. '{--image-logo:url(%2$s);--image-logoheader:url(%2$s)}'
			. '@media(prefers-color-scheme:dark){body[data-theme-default]'
			. '{--image-logo:url(%2$s);--image-logoheader:url(%2$s)}}',
			$light,
			$dark
		));

		$this->emitResidencyLabel();

		// The favicon is the one brand mark with no CSS variable behind it, so it
		// has to go in as a real <link>. Declared after core's own, which wins on
		// document order for same-rel icons.
		Util::addHeader('link', [
			'rel'  => 'icon',
			'type' => 'image/svg+xml',
			'href' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/favicon.svg'),
		]);
	}

	/**
	 * The residency badge in the header bar.
	 *
	 * Every mockup screen carries a green pill in the chrome reading "IN . Mumbai",
	 * and the design notes are explicit that this is the point: "Residency is a
	 * column, not a footer claim." The pill itself is drawn in bharatsuite.css off
	 * .header-end::before; all this does is supply the text.
	 *
	 * Read from the environment rather than derived from the S3 endpoint. A Ceph
	 * zone name says where a bucket is, not which jurisdiction the operator is
	 * claiming, and this pill is a claim -- so it is the operator's to make, and an
	 * operator who has not made it should get no badge rather than a guess. Unset
	 * or blank emits nothing at all, and `content: var(--bs-residency-label, none)`
	 * then resolves to `none`, so the pseudo-element does not render.
	 *
	 * A <meta> plus a script, NOT the inline <style> the logo variants above use.
	 * Util::addHeader() escapes its text for HTML, and `content` needs a quoted CSS
	 * string, so the style route emitted
	 *
	 *     :root:root{--bs-residency-label:&quot;IN . Mumbai&quot;}
	 *
	 * where the semicolon inside the entity terminated the declaration and the value
	 * became the token `&quot`. CSS has no unquoted form for `content`, so there is
	 * no way to emit this as a stylesheet through that API. In an ATTRIBUTE the same
	 * escaping is exactly what is wanted -- it round-trips any text unchanged -- so
	 * the label goes in a meta tag and js/residency-badge.js moves it into the custom
	 * property, where it never passes through markup at all.
	 */
	private function emitResidencyLabel(): void {
		$label = trim((string)getenv(self::ENV_RESIDENCY_LABEL));
		if ($label === '') {
			return;
		}

		// A newline in a <meta content> attribute is legal but arrives normalised in
		// unhelpful ways, and this is a one-line badge, so collapse them here.
		$label = trim(str_replace(["\r", "\n", "\t"], ' ', $label));
		if ($label === '') {
			return;
		}

		Util::addHeader('meta', [
			'name'    => 'bharatsuite-residency',
			'content' => $label,
		]);

		Util::addScript(Application::APP_ID, 'residency-badge');
	}
}
