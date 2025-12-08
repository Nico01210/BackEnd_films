<?php

namespace App\State;

use App\Entity\Review;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<Review, Review|void>
 */
final readonly class ReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * @param Review $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Valider que c'est une Review
        if (!$data instanceof Review) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();

        // Si c'est un POST (création), vérifier qu'il n'existe pas déjà une review
        if ($operation->getMethod() === 'POST') {
            $existingReview = $this->entityManager->getRepository(Review::class)->findOneBy([
                'user' => $user,
                'movie' => $data->getMovie()
            ]);

            if ($existingReview) {
                throw new BadRequestHttpException(
                    'Vous avez déjà commenté ce film. Vous pouvez seulement modifier ou supprimer votre commentaire existant.'
                );
            }
        }

        // Définir automatiquement l'utilisateur courant
        $data->setUser($user);

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
