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
		// Two variants: the wordmark's "Suite" is ink and disappears on a dark ground,
		// so the dark theme gets a version with "Suite" in paper. The selectors mirror
		// Nextcloud's three theme states.
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

		// The favicon is the one brand mark with no CSS variable behind it, so it
		// has to go in as a real <link>. Declared after core's own, which wins on
		// document order for same-rel icons.
		Util::addHeader('link', [
			'rel'  => 'icon',
			'type' => 'image/svg+xml',
			'href' => $this->urlGenerator->linkTo(Application::APP_ID, 'img/favicon.svg'),
		]);
	}
}
