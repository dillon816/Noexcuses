<?php

namespace App\Controller;

use App\Service\DataExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Export RGPD des données personnelles de l'utilisateur connecté (JSON et Excel).
 * L'utilisateur est toujours récupéré depuis le JWT (jamais un id fourni par le client),
 * ce qui garantit qu'on ne peut exporter que ses propres données.
 */
#[Route('/api/profil/export')]
class ExportController extends AbstractController
{
    public function __construct(private readonly DataExportService $exportService) {}

    #[Route('/json', name: 'api_export_json', methods: ['GET'])]
    public function exportJson(): JsonResponse
    {
        $data = $this->exportService->collect($this->getUser());

        $response = new JsonResponse($data);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="noexcuses_mes_donnees_' . date('Y-m-d') . '.json"',
        );

        return $response;
    }

    #[Route('/excel', name: 'api_export_excel', methods: ['GET'])]
    public function exportExcel(): Response
    {
        $data = $this->exportService->collect($this->getUser());
        $path = $this->exportService->toXlsxFile($data);
        $content = file_get_contents($path);
        @unlink($path);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="noexcuses_mes_donnees_' . date('Y-m-d') . '.xlsx"',
        );

        return $response;
    }
}
