<?php

namespace App\State;

use App\Entity\Review;
use App\Entity\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;


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

        /** @var User|UserInterface|null $user */
        $user = $this->security->getUser();
        /** @var string|null $method */
        $method = $operation->getMethod();

        // Si c'est un POST (création), vérifier qu'il n'existe pas déjà une review
        if ($method === 'POST') {
            // Récupérer le movie - soit depuis les données, soit depuis l'URI
            $movie = $data->getMovie();
            if (!$movie && isset($uriVariables['movieId'])) {
                $movie = $this->entityManager->find('App\Entity\Movie', $uriVariables['movieId']);
                if ($movie) {
                    $data->setMovie($movie);
                }
            }

            if ($movie && $user) {
                $existingReview = $this->entityManager->getRepository(Review::class)->findOneBy([
                    'user' => $user,
                    'movie' => $movie
                ]);

                if ($existingReview) {
                    throw new BadRequestHttpException(
                        'Vous avez déjà commenté ce film. Utilisez PATCH pour modifier votre commentaire existant.'
                    );
                }
            }
        }

        // Définir automatiquement l'utilisateur courant
        $data->setUser($user);

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
