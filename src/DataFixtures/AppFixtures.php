<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $listAuthors = [];
        for ($i = 0; $i < 10; $i++) {
            $author = new Author();
            $author->setFirstName('Prénom ' . $i);
            $author->setLastName('Nom ' . $i);
            $manager->persist($author);
            $listAuthors[] = $author;
        }

        for ($i = 0; $i < 100; $i++) {
            $book = new Book();
            $book->setTitle('Titre ' . $i);
            $book->setCoverText('Texte de couverture ' . $i);
            $book->setAuthor($listAuthors[array_rand($listAuthors)]);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
