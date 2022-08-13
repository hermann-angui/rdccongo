<?php

namespace App\Controller\Api;

use App\Entity\Application;
use App\Helper\UserHelper;
use App\Helper\VisaHelper;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\EVisaImageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/visa')]
class VisaApplicationController extends AbstractController
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

    #[Route('/', name: 'api_visa_applications', methods: ['GET','POST'])]
    public function findAll(Request $request): JsonResponse
    {
        $all = $this->applicationRepository->findAll();
        $serializer = $this->container->get('serializer');
        $serializer->setCircularReferenceLimit(2);
        $data = $serializer->serialize($all, 'json');
        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_visa_application', methods: ['GET','POST'])]
    public function getApplication(Request $request, Application $application): JsonResponse
    {
        $serializer = $this->container->get('serializer');
        $serializer->setCircularReferenceLimit(2);
        $data = $serializer->serialize($application, 'json');
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
    public function attachments(Request $request, Application $application,  Packages $assetsManager, UserHelper $userHelper): JsonResponse
    {
        $data["applicant_dir"] = $userHelper->getUserUploadDirectory($application->getApplicant());
        $data["site_dir_root"] = $userHelper->getPublicDirectory($application->getApplicant());

        $basePath = 'users/' . $application->getApplicant()->getId() . '/';
        $url = $this->generateUrl('app_home', [], urlGeneratorInterface::ABSOLUTE_URL);
        $data["photo_url"] = trim($url , '/') . $assetsManager->getUrl($basePath . $application->getPhoto());
        $data["passport_scan"] = trim($url , '/') . $assetsManager->getUrl($basePath . $application->getPassportScan());
        $data["invitation_letter_scan"] = trim($url , '/') . $assetsManager->getUrl($basePath . $application->getInvitationLetterScan());
        $data["hotel_reservation_scan"] = trim($url , '/') . $assetsManager->getUrl($basePath . $application->getHotelReservationScan());
        $data["flight_ticket_scan"] = trim($url , '/') . $assetsManager->getUrl($basePath . $application->getFlightTicketScan());
        $data["visa"] = trim($url , '/') .  $assetsManager->getUrl($basePath . $application->getApplicationNumber() . '.jpg');

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
