<?php

namespace App\DataFixtures\Faker;

use Faker\Provider\Base;

/**
 * Homemade Faker provider: plausible wardrobe items instead of lorem ipsum.
 */
final class WardrobeProvider extends Base
{
    private const CLOTHES = ['Pull', 'Veste', 'Jean', 'Chemise', 'Robe', 'Manteau', 'Sweat', 'T-shirt', 'Jupe', 'Blazer', 'Gilet', 'Pantalon', 'Trench', 'Doudoune'];
    private const MATERIALS = ['en laine', 'en lin', 'en coton', 'en jean', 'en cachemire', 'en velours', 'en soie'];
    // invariable colours: no gender agreement needed (« un pull kaki », « une robe kaki »)
    private const COLORS = ['bordeaux', 'kaki', 'marron', 'beige', 'bleu marine', 'orange', 'rouge', 'cognac', 'crème'];
    private const BRANDS = ['Sézane', 'Zara', 'Uniqlo', 'Petit Bateau', 'Levi\'s', 'Maje', 'Sandro', 'Mango', 'Vintage', 'Arket', 'Aigle'];
    private const BOOKS = ['L\'Étranger', 'Les Misérables', 'Le Petit Prince', 'Madame Bovary', 'L\'Alchimiste', 'Germinal', 'Bel-Ami', 'La Peste', 'Le Comte de Monte-Cristo', 'Vingt mille lieues sous les mers'];
    private const ARTISTS = ['Daft Punk', 'Kendrick Lamar', 'Nas', 'Outkast', 'Fela Kuti', 'Fairuz', 'Oum Kalthoum', 'Serge Gainsbourg', 'Aya Nakamura', 'IAM', 'Stromae', 'Nina Simone'];

    /**
     * @return array{name: string, color: string, brand: string}
     */
    public function clothing(): array
    {
        return [
            'name' => self::randomElement(self::CLOTHES).' '.self::randomElement(self::MATERIALS),
            'color' => self::randomElement(self::COLORS),
            'brand' => self::randomElement(self::BRANDS),
        ];
    }

    public function bookTitle(): string
    {
        return self::randomElement(self::BOOKS);
    }

    public function vinylArtist(): string
    {
        return self::randomElement(self::ARTISTS);
    }
}
