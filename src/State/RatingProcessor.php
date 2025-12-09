<?php

namespace App\State;

use App\Entity\Rating;
use App\Entity\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @implements ProcessorInterface<Rating, Rating|void>
 */
final readonly class RatingProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * @param Rating $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Rating) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        /** @var User|UserInterface|null $user */
        $user = $this->security->getUser();
        /** @var string $method */
        $method = $operation->getMethod();

        // Pour POST (création), vérifier qu'il n'existe pas déjà une note
        if ($method === 'POST') {
            // Vérifier que les entités User et Movie existent bien
            $movieId = $data->getMovie() ? $data->getMovie()->getId() : null;
            $managedMovie = $movieId ? $this->entityManager->find('App\Entity\Movie', $movieId) : null;
            
            if (!$managedMovie) {
                throw new BadRequestHttpException('Film non trouvé.');
            }
            
            // Obtenir l'utilisateur authentifié du système de sécurité
            /** @var User $user */
            $managedUser = $this->entityManager->find('App\Entity\User', $user->getId());
            if (!$managedUser) {
                throw new BadRequestHttpException('Utilisateur non trouvé.');
            }
            
            // Chercher les ratings existants
            $existingRating = $this->entityManager->getRepository(Rating::class)->findOneBy([
                'user' => $managedUser,
                'movie' => $managedMovie
            ]);

            if ($existingRating) {
                throw new BadRequestHttpException(
                    'Vous avez déjà noté ce film. Utilisez PATCH pour modifier votre note existante.'
                );
            }
            
            // Utiliser les entités gérées par Doctrine
            $data->setUser($managedUser);
            $data->setMovie($managedMovie);
        }

        // Pour PATCH et DELETE, vérifier que l'utilisateur est propriétaire
        if (in_array($method, ['PATCH', 'DELETE'])) {

            if ($data->getUser()->getId() !== $user->getId()) {
                throw new AccessDeniedHttpException('Vous ne pouvez modifier que vos propres notes.');
            }
        }

        // Traiter la requête avec le processeur par défaut
        $result = $this->processor->process($data, $operation, $uriVariables, $context);

        // Pour POST, nettoyer le cache Doctrine après création réussie
        if ($method === 'POST') {
            $this->entityManager->clear(Rating::class);
        }

        return $result;
    }
}
