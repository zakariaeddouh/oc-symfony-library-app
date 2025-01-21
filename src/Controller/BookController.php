<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BookController extends AbstractController
{
    /**
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book_list', methods: ['GET'])]
    public function list(BookRepository $bookRepository,
    SerializerInterface $serializer): JsonResponse
    {
        $booksList = $bookRepository->findAll();

        $jsonBookList = $serializer->serialize($booksList, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * @param Book $book
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_show', methods: ['GET'])]
    public function show(Book $book, 
        BookRepository $bookRepository, 
        SerializerInterface $serializer): JsonResponse
    {
        $book = $bookRepository->find($book);

        if (!$book) {
            return new JsonResponse('Book not found', Response::HTTP_NOT_FOUND);
        }

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * @param Book $book
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_delete', methods: ['DELETE'])]
    public function delete(Book $book, 
        EntityManagerInterface $em, 
        SerializerInterface $serializer): JsonResponse
    {
        $em->remove($book);
        $em->flush();

        return new JsonResponse('null', Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book_add', methods: ['POST'])]
    public function create(Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $errors = $validator->validate($book);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($book);
        $em->flush();

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate('book_show', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * @param Request $request
     * @param Book $book
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_update', methods: ['PUT'])]
    public function update(Request $request, 
        Book $currentBook, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator): JsonResponse
    {
        $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);
        
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        $errors = $validator->validate($updatedBook);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($updatedBook);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
