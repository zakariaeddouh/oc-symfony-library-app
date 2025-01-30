<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
 
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('user@bookapi.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        $admin = new User();
        $admin->setEmail('admin@bookapi.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->userPasswordHasher->hashPassword($admin, 'password'));
        $manager->persist($admin);
        
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
            $book->setComment('Commentaire du livre ' . $i);
            $book->setAuthor($listAuthors[array_rand($listAuthors)]);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
