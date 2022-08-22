<?php

namespace App\Controller;

use App\Entity\Application;
use App\Form\ApplicationFormType;
use App\Helper\UserHelper;
use App\Helper\VisaHelper;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\EVisaImageGenerator;
use App\Traits\UploadDocumentTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/visa')]
class VisaController extends AbstractController
{
    use UploadDocumentTrait;

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
    #[Route('/apply', name: 'application_apply', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $session = $request->getSession();
        $application = new Application();

        if($user = $this->getUser()){
            $application->setFirstName($user->getFirstName());
            $application->setLastName($user->getLastName());
        }
        if(!$request->get('parent_application')){
            $request->getSession()->set('parent_application', $request->get('parent_application'));
        }
        if(!$session->get('current_application')) $session->set('current_application', uniqid());
        $form = $this->createForm(ApplicationFormType::class, $application);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            $application->setApplicationNumber($session->get('current_application', uniqid()));
            $application->setStatus('WAITING_FOR_REVIEW');

            if($session->get('parent_application')) {
                $parent = $this->applicationRepository->find($session->get('parent_application'));
                if($parent) $application->setParent($parent);
            }

            $photo = $form->get('photo')->getData();
            $fileName = $this->userHelper->storeUserAsset($photo, $user);
            $application->setPhoto($fileName);

            $passport_scan = $form->get('passport_scan')->getData();
            $fileName = $this->userHelper->storeUserAsset($passport_scan, $user);
            $application->setPassportScan($fileName);

            $invitation_letter_scan = $form->get('invitation_letter_scan')->getData();
            $fileName = $this->userHelper->storeUserAsset($invitation_letter_scan, $user);
            $application->setInvitationLetterScan($fileName);

            $flight_ticket_scan = $form->get('flight_ticket_scan')->getData();
            $fileName = $this->userHelper->storeUserAsset($flight_ticket_scan, $user);
            $application->setFlightTicketScan($fileName);

//          $hotel_reservation_scan = $form->get('flight_ticket_scan')->getData();
//          $fileName = $userHelper->storeUserAsset($hotel_reservation_scan, $user);
//          $application->setHotelReservationScan($fileName);

            $application->setApplicant($user);

            $data["application_number"] = $application->getApplicationNumber();
            $data["userDir"] =  $this->userHelper->getUserUploadDirectory($application->getApplicant());
            $this->eVisaImageGenerator->generateBarCode($data);

            $application->setDateCreated(new \DateTime());
            $application->setApplicationDate(new \DateTime());
            $this->applicationRepository->add($application, true);

            if($request->isXmlHttpRequest()) {
                return new JsonResponse("success");
            }
            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }
        return $this->renderForm('visa/apply.html.twig', [
            'visa_application' => $application,
            'form' => $form,
            'application_number' => $session->get('current_application'),
            'parent_application' => $session->get('parent_application')
        ]);
    }

    #[Route('/check_status', name: 'application_check_status')]
    public function checkStatus(Request $request): Response
    {
        $applications = $this->applicationRepository->findByApplicant($this->getUser());
        return $this->render('visa/check_status.html.twig', ['applications' => $applications]);
    }

    #[Route('/check_eligibility', name: 'app_application_check_eligibility')]
    public function checkEligibility(Request $request): Response
    {
        return $this->render('visa/check_eligibility.html.twig');
    }

    #[Route('/details/{id}', name: 'application_details', methods: ['GET','POST'])]
    public function details(Request $request, Application $application): Response
    {
        $data["application_number"] = $application->getApplicationNumber();
        $data["userDir"] =  $this->userHelper->getUserUploadDirectory($application->getApplicant());
        $this->eVisaImageGenerator->generateBarCode($data);

        $documents = $this->getApplicationAttachmentsUrl($application);

        return $this->render('visa/application_details.html.twig', [
            'application' => $application,
            "documents" => $documents
        ]);
    }

    #[Route('/review_summary', name: 'application_review_summary', methods: ['GET','POST'])]
    public function reviewSummary(Request $request): Response
    {
        $user = $this->getUser();
        $files = $request->files->get('application_form');
        $basePath = 'users/' . $user->getId() . '/tmp/';
        $url = $this->generateUrl('app_home', [], urlGeneratorInterface::ABSOLUTE_URL);

        $application = $request->get('application_form');
        $application["hotel_reservation_scan"] = null;
        $application["photo_url"] = null;
        $application["invitation_letter_scan"] = null;
        $application["flight_ticket_scan"] = null;
        $application["passport_scan"] = null;

        if(isset($files['photo'])){
            $photo = $files['photo'];
            $fileName = $this->userHelper->storeUserTempAsset($photo, $user);
            $application["photo_url"] = $url . $basePath . $fileName;
        }
        if(isset($files['passport_scan'])){
            $passport_scan = $files['passport_scan'];
            $fileName = $this->userHelper->storeUserTempAsset($passport_scan, $user);
            $application["passport_scan"] = $url . $basePath . $fileName;
        }
        if(isset($files['invitation_letter_scan'])){
            $invitation_letter_scan = $files['invitation_letter_scan'];
            $fileName = $this->userHelper->storeUserTempAsset($invitation_letter_scan, $user);
            $application["invitation_letter_scan"] = $url . $basePath . $fileName;
        }
        if(isset($files['flight_ticket_scan'])){
            $flight_ticket_scan = $files['flight_ticket_scan'];
            $fileName = $this->userHelper->storeUserTempAsset($flight_ticket_scan, $user);
            $application["flight_ticket_scan"] = $url . $basePath . $fileName;
        }
        if(isset($files['hotel_reservation_scan'])){
            $application["hotel_reservation_scan"] = null;
        }

        return $this->render('visa/review_summary.html.twig', ['application' => $application]);
    }

    #[Route('/pay', name: 'app_application_pay', methods: ['GET'])]
    public function pay(Request $request): Response
    {
        return $this->render('visa/pay.html.twig');
    }
}
