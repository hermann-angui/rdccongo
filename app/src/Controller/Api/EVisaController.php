<?php

namespace App\Controller\Api;

use App\Entity\Application;
use App\Helper\VisaHelper;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\EVisaImageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/evisa')]
class EVisaController extends AbstractController
{
    protected UserRepository $userRepository;
    protected ApplicationRepository $applicationRepository;
    protected EVisaImageGenerator $eVisaImageGenerator;
    protected VisaHelper $visaHelper;

    public function __construct(UserRepository $userRepository,
                                ApplicationRepository $applicationRepository,
                                VisaHelper $visaHelper,
                                EVisaImageGenerator $eVisaImageGenerator)
    {
        $this->visaHelper = $visaHelper;
        $this->userRepository = $userRepository;
        $this->applicationRepository = $applicationRepository;
        $this->eVisaImageGenerator = $eVisaImageGenerator;
    }

    #[Route('/generate/{id}', name: 'api_visa_generate', methods: ['GET'])]
    public function generate(Request $request, Application $application, ApplicationRepository $applicationRepository): Response
    {
        $application->setStatus('APPROVED');
        $applicationRepository->add($application, true);

        if(!($application->getStatus()==='APPROVED') ) return $this->json(['error' => 1]);
        $visaParams = $this->visaHelper->mapApplicationToVisa($application);
        return $response = $this->eVisaImageGenerator->generate($visaParams);
        // return $this->json($response);
    }

}
