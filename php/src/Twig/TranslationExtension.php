<?php
declare(strict_types=1);

namespace AIO\Twig;

use AIO\Translation\TranslationManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TranslationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly TranslationManager $translationManager,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->translate(...)),
        ];
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('t', $this->translate(...)),
        ];
    }

    #[\Override]
    public function getGlobals(): array
    {
        return [
            'currentLanguage'    => $this->translationManager->getCurrentLanguage(),
            'supportedLanguages' => $this->translationManager->getSupportedLanguages(),
        ];
    }

    public function translate(string $key): string
    {
        return $this->translationManager->translate($key);
    }
}