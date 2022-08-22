<?php

namespace App\Controller\Api;

use App\Entity\Application;
use App\Helper\VisaHelper;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\EVisaImageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/visa')]
class EVisaController extends AbstractController
{
    protected UserRepository $userRepository;
    protected ApplicationRepository $applicationRepository;
    protected VisaHelper $visaHelper;

    public function __construct(UserRepository $userRepository,
                                ApplicationRepository $applicationRepository,
                                VisaHelper $visaHelper,
                                )
    {
        $this->visaHelper = $visaHelper;
        $this->userRepository = $userRepository;
        $this->applicationRepository = $applicationRepository;
    }

    #[Route('/generate/{id}', name: 'api_visa_generate', methods: ['GET'])]
    public function generate(Request $request, Application $application, ApplicationRepository $applicationRepository): Response
    {
        $application->setStatus('APPROVED');
        $applicationRepository->add($application, true);

        if(!($application->getStatus()==='APPROVED')) return $this->json(['error' => 1]);
        $response = $this->visaHelper->generate($application);
        $response = new JsonResponse($response);
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES);
        return $response;
    }

}
