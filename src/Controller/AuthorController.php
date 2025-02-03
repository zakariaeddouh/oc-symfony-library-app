<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use ApiPlatform\Metadata\ApiResource as MetadataApiResource;

#[OA\Tag(name:"Author")]
Class AuthorController extends AbstractController
{
    /**
     * This method returns a list of authors
     * 
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[OA\Response(
        response: 200,
        description: 'This method returns a list of authors',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Author::class))
        )
    )]
    #[OA\Parameter(
        name:"page",
        in:"query",
        description:"La page que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name:"limit",
        in:"query",
        description:"Le nombre d'éléments que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[Route('/api/authors', name: 'author_list', methods: ['GET'])]
    public function list(AuthorRepository $authorRepository, 
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        
        $idCache = "getAllAuthor-" . $page . "-" . $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            $item->tag("booksCache");
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getAuthors"]);
            return $serializer->serialize($authorList, 'json', $context);
        });
        
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    /**
     * This method returns an author
     * 
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
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * This method deletes an author
     * 
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
     * This method adds an author
     * 
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
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
        
        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        
        $em->persist($author);
        $em->flush();
        
        $cache->invalidateTags(["booksCache"]);

        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        $location = $urlGenerator->generate('author_show', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);	
    }

    /**
     * This method updates an author
     * 
     * @param Request $request
     * @param Author $currentAuthor
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
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache): JsonResponse
    {
        $errors = $validator->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $currentAuthor->setFirstName($newAuthor->getFirstName());
        $currentAuthor->setLastName($newAuthor->getLastName());
        
        $em->persist($currentAuthor);
        $em->flush();

        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
