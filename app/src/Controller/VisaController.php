<?php

namespace App\Controller;

use App\Entity\Application;
use App\Form\ApplicationFormType;
use App\Helper\UserHelper;
use App\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/visa')]
class VisaController extends AbstractController
{
    #[Route('/apply', name: 'app_application_new', methods: ['GET','POST'])]
    public function new(Request $request, ApplicationRepository $applicationRepository, UserHelper $userHelper): Response
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
                $parent = $this->container->get('doctrine')
                    ->getRepository(Application::class)
                    ->find($session->get('parent_application'));
                if($parent) $application->setParent($parent);
            }

            $photo = $form->get('photo')->getData();
            $fileName = $userHelper->storeUserAsset($photo, $user);
            $application->setPhoto($fileName);

            $passport_scan = $form->get('passport_scan')->getData();
            $fileName = $userHelper->storeUserAsset($passport_scan, $user);
            $application->setPassportScan($fileName);

            $invitation_letter_scan = $form->get('invitation_letter_scan')->getData();
            $fileName = $userHelper->storeUserAsset($invitation_letter_scan, $user);
            $application->setInvitationLetterScan($fileName);

            $flight_ticket_scan = $form->get('flight_ticket_scan')->getData();
            $fileName = $userHelper->storeUserAsset($flight_ticket_scan, $user);
            $application->setFlightTicketScan($fileName);

//            $hotel_reservation_scan = $form->get('flight_ticket_scan')->getData();
//            $fileName = $userHelper->storeUserAsset($hotel_reservation_scan, $user);
//            $application->setHotelReservationScan($fileName);

            $application->setApplicant($user);

            $application->setDateCreated(new \DateTime());
            $applicationRepository->add($application, true);

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

    #[Route('/check_status', name: 'app_application_check_status')]
    public function checkStatus(Request $request, ApplicationRepository $applicationRepository): Response
    {
        $applications = $applicationRepository->findByApplicant($this->getUser());
        return $this->render('visa/check_status.html.twig', ['applications' => $applications]);
    }

    #[Route('/check_eligibility', name: 'app_application_check_eligibility')]
    public function checkEligibility(Request $request, ApplicationRepository $applicationRepository): Response
    {
        return $this->render('visa/check_eligibility.html.twig');
    }

    #[Route('/review', name: 'app_application_review', methods: ['GET'])]
    public function review(Request $request, ApplicationRepository $applicationRepository): Response
    {
        $applications = $applicationRepository->findByApplicant($this->getUser());
        return $this->render('visa/review.html.twig', ['applications' => $applications]);
    }


    #[Route('/review_summary', name: 'app_application_review_summary', methods: ['POST'])]
    public function reviewSummary(Request $request): Response
    {
        $application = $request->get('application_form');
        $application["fullName"] = $application["firstname"] . ' ' . $application["lastname"];
        return $this->render('visa/review_summary.html.twig', ['application' => $application]);
    }

    #[Route('/pay', name: 'app_application_pay', methods: ['GET'])]
    public function pay(Request $request, ApplicationRepository $applicationRepository): Response
    {
        return $this->render('visa/pay.html.twig');
    }

}
