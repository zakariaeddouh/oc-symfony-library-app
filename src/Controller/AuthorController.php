<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

Class AuthorController extends AbstractController
{
    /**
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'author_list', methods: ['GET'])]
    public function list(AuthorRepository $authorRepository, 
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 1);

        $idCache = "getAllAuthors-" . $page . "-" . $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            $item->tag("authorsCache");
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);
        });

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    /**
     * @param Author $author
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'author_show', methods: ['GET'])]
    public function show(Author $author, 
        AuthorRepository $authorRepository, 
        SerializerInterface $serializer): JsonResponse
    {
        $author = $authorRepository->find($author);

        if (!$author) {
            return new JsonResponse('Author not found', Response::HTTP_NOT_FOUND);
        }

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * @param Author $author
     * @param AuthorRepository $authorRepository
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'author_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function delete(Author $author, 
        AuthorRepository $authorRepository, 
        EntityManagerInterface $em,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $em->remove($author);
        $em->flush();

        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'author_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function add(Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em, 
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $errors = $validator->validate($author);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        $location = $urlGenerator->generate('author_show', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);	
    }

    /**
     * @param Request $request
     * @param Author $author
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'author_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous devez être administrateur pour accéder à cette ressource')]
    public function update(Request $request, 
        Author $currentAuthor, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em,
        ValidatorInterface $validator): JsonResponse
    {
        $updatedAuthor = $serializer->deserialize($request->getContent(), 
            Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        $errors = $validator->validate($updatedAuthor);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
