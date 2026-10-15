<?php

namespace App\Controller;

use App\Entity\Item;
use App\Security\Voter\ItemVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/items')]
final class ItemController extends AbstractController
{
    #[Route('/{id}', name: 'api_item_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Item $item): JsonResponse
    {
        $this->denyAccessUnlessGranted(ItemVoter::VIEW, $item);

        return $this->json($item, context: ['groups' => ['item:read']]);
    }
}