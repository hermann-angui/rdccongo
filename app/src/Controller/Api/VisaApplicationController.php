<?php

namespace App\Controller\Api;

use App\Entity\Application;
use App\Helper\UserHelper;
use App\Helper\VisaHelper;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\EVisaImageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;


#[Route('/api/application')]
class VisaApplicationController extends AbstractController
{
    protected UserRepository $userRepository;
    protected ApplicationRepository $applicationRepository;
    protected EVisaImageGenerator $eVisaImageGenerator;
    protected VisaHelper $visaHelper;
    protected SerializerInterface $serializer;
    protected UserHelper $userHelper;


    public function __construct(UserRepository $userRepository,
                                ApplicationRepository $applicationRepository,
                                VisaHelper $visaHelper,
                                EVisaImageGenerator $eVisaImageGenerator,
                                SerializerInterface $serializer,
                                UserHelper $userHelper)
    {
        $this->visaHelper = $visaHelper;
        $this->userRepository = $userRepository;
        $this->applicationRepository = $applicationRepository;
        $this->eVisaImageGenerator = $eVisaImageGenerator;
        $this->serializer = $serializer;
        $this->userHelper = $userHelper;

    }

    #[Route('/', name: 'api_visa_applications', methods: ['GET','POST'])]
    public function findAll(Request $request): JsonResponse
    {
        $all = $this->applicationRepository->findAll();
        $data = $this->serializer->serialize($all, 'json', ['groups' => 'application']);
        $data = json_decode($data, true);
        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_visa_application', methods: ['GET','POST'])]
    public function getApplication(Application $application): JsonResponse
    {
        $data = $this->serializer->serialize($application, 'json', ['groups' => 'application']);
        $data = json_decode($data, true);
        $attachments = $this->userHelper->getApplicationAttachmentsUrl($application);
        $data = array_merge($data, $attachments);
        return $this->json($data);
    }

    #[Route('/approve/{id}', name: 'api_visa_application_approve', methods: ['GET','PUT','POST'])]
    public function approve(Request $request, Application $application): JsonResponse
    {
        $application->setStatus('APPROVED');
        $this->applicationRepository->add($application, true);
        return $this->json($application->getStatus());
    }

    #[Route('/deny/{id}', name: 'api_visa_application_deny', methods: ['GET','PUT','POST'])]
    public function deny(Request $request, Application $application): JsonResponse
    {
        $data = $this->updateStatus($application, 'DENIED');
        return $this->json($data);
    }

    #[Route('/needmore/{id}', name: 'api_visa_application_need_more', methods: ['GET','PUT','POST'])]
    public function needMore(Request $request, Application $application): JsonResponse
    {
        $data = $this->updateStatus($application, 'NEED_MORE_INFO');
        return $this->json($data);
    }

    #[Route('/status/{id}', name: 'api_visa_application_status', methods: ['GET','PUT','POST'])]
    public function index(Request $request, Application $application): JsonResponse
    {
        return $this->json($application->getStatus());
    }

    #[Route('/attached_document/{id}', name: 'api_visa_application_attachments', methods: ['GET','PUT','POST'])]
    public function attachments(Request $request, Application $application): JsonResponse
    {
        $data = $this->userHelper->getApplicationAttachmentsUrl($application);
        $response = new JsonResponse($data);
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES);

        return $response;
    }

    private function updateStatus(&$application, $value){
        $oldStatus= $application->getStatus();
        $application->setStatus($value);
        $this->applicationRepository->add($application, true);
        $data = ["old" => $oldStatus, "new" => $application->getStatus()];
        return $data;
    }
}
