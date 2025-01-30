<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class BookController extends AbstractController
{
    /**
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book_list', methods: ['GET'])]
    public function list(BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getBooks"]);
            return $serializer->serialize($bookList, 'json', $context);
        });
        
        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * @param Book $book
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_show', methods: ['GET'])]
    public function show(Book $book,
        SerializerInterface $serializer,
        VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * @param Book $book
     * @param EntityManagerInterface $entityManager
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function delete(Book $book, 
        EntityManagerInterface $em, 
        TagAwareCacheInterface $cache): JsonResponse
    {
        $em->remove($book);
        $em->flush();

        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param AuthorRepository $authorRepository
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function create(Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        $cache->invalidateTags(["booksCache"]);

        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $jsonBook = $serializer->serialize($book, 'json', $context);
		
        $location = $urlGenerator->generate('book_show', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);	
    }

    /**
     * @param Request $request
     * @param Book $currentBook
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param AuthorRepository $authorRepository
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'book_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function update(Request $request, 
        Book $currentBook, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $currentBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($currentBook);
        $em->flush();
        
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
