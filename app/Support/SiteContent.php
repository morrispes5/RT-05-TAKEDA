<?php

namespace App\Support;

class SiteContent
{
    public static function photos(): array
    {
        return json_decode(file_get_contents(resource_path('data/dokumentasi.json')), true, flags: JSON_THROW_ON_ERROR)['foto'];
    }

    public static function articles(): array
    {
        $articles = json_decode(file_get_contents(resource_path('data/artikel.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($articles as &$article) {
            $text = $article['intro'].' '.implode(' ', array_column($article['sections'], 'body'));
            $article['minutes'] = max(1, (int) ceil(count(preg_split('/\\s+/u', trim($text))) / 180));
        }

        return $articles;
    }

    public static function albums(): array
    {
        return json_decode(file_get_contents(resource_path('data/album.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function paths(): array
    {
        return array_merge(
            ['/', '/profil', '/fasilitas', '/dokumentasi', '/artikel', '/kontak', '/pengurus'],
            array_map(fn ($a) => '/artikel/'.$a['slug'], self::articles()),
            array_map(fn ($a) => '/dokumentasi/'.$a['slug'], self::albums()),
            array_map(fn ($screen) => '/pengurus/'.$screen, ['masuk', 'artikel', 'artikel/editor', 'dokumentasi', 'dokumentasi/editor', 'pratinjau'])
        );
    }
}
