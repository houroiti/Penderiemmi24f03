<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stored as a short string in the database; translated when displayed.
 */
enum ItemCategory: string implements TranslatableInterface
{
    case Clothing = 'clothing';
    case Book = 'book';
    case Vinyl = 'vinyl';
    case Other = 'other';

    /**
     * Translation key => case, the format expected by the back-office choice fields.
     *
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices['category.'.$case->value] = $case;
        }

        return $choices;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('category.'.$this->value, locale: $locale);
    }
}
