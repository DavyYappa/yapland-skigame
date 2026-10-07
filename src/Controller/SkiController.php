<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Score;
use App\Repository\ClientRepository;
use App\Repository\ScoreRepository;
use App\Service\Campaign;
use App\Service\PlayerNames;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The game a client's colleagues play. Unknown tokens, offline clients and
 * anything after the campaign all answer the same 404.
 */
#[Route('/ski/{token}', requirements: ['token' => '[a-z0-9]{12}'])]
class SkiController extends AbstractController
{
    /** The game cannot score faster than this; anything above it was not played. */
    private const MAX_POINTS_PER_SECOND = 40;

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly ScoreRepository $scores,
        private readonly Campaign $campaign,
    ) {
    }

    #[Route('', name: 'ski_play', methods: ['GET'])]
    public function play(string $token, Request $request, PlayerNames $names): Response
    {
        $client = $this->findLiveClient($token);
        $session = $request->getSession();

        // The server picks the name, once per visitor and game
        $nameKey = 'ski.'.$token.'.name';
        if (!$session->has($nameKey)) {
            $session->set($nameKey, $names->random());
        }
        $session->set('ski.'.$token.'.loaded_at', time());

        $response = $this->render('ski/play.html.twig', [
            'client' => $client,
            'player_name' => $session->get($nameKey),
            'score_url' => $this->generateUrl('ski_score', ['token' => $token]),
            'top' => $this->scores->top($client),
            'preview' => false,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    #[Route('/score', name: 'ski_score', methods: ['POST'])]
    public function score(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire(service: 'limiter.score_submit')] RateLimiterFactoryInterface $scoreLimiter,
    ): JsonResponse {
        $client = $this->findLiveClient($token);
        $session = $request->getSession();
        $name = $session->get('ski.'.$token.'.name');
        $loadedAt = $session->get('ski.'.$token.'.loaded_at');

        if (!\is_string($name) || !\is_int($loadedAt)) {
            return $this->json(['error' => 'Laad het spel opnieuw.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$scoreLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $this->json(['error' => 'Even op adem komen. Probeer het zo meteen opnieuw.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $points = $request->getPayload()->get('points');
        $maxPossible = (time() - $loadedAt + 1) * self::MAX_POINTS_PER_SECOND;
        if (!\is_int($points) || $points < 0 || $points > $maxPossible) {
            return $this->json(['error' => 'Deze score kunnen we niet aannemen.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->persist(new Score($client, $name, $points));
        $em->flush();

        return $this->json([
            'top' => array_map(static fn (Score $s) => [
                'name' => $s->getPlayerName(),
                'points' => $s->getPoints(),
                'me' => $s->getPlayerName() === $name,
            ], $this->scores->top($client)),
        ]);
    }

    private function findLiveClient(string $token): Client
    {
        $client = $this->clients->findOneBy(['token' => $token]);
        if (null === $client || !$client->isActive() || $this->campaign->isOver()) {
            throw $this->createNotFoundException();
        }

        return $client;
    }
}
