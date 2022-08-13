<?php

namespace App\Helper;

use App\Entity\Application;
use TCPDF2DBarcode;

class VisaHelper
{
    protected UserHelper $userHelper;


    /**
     * @var string
     */
    private string $rootDir;
    /**
     * @param UserHelper $userHelper
     */
    public function __construct(UserHelper $userHelper)
    {
        $this->userHelper = $userHelper;
    }

    public function mapApplicationToVisa(?Application $application): ?array
    {
        $visaParams["place_of_issue"] =  'EMBASSY OF RDC WASHINGTON';
        $visaParams["number_of_entries"] =  $application->getVisaType(); //'Plusieurs fois';
        $visaParams["type"] =  $application->getPurposeOfTravel(); // 'Ordinaire';
        $visaParams["surname"] =  $application->getLastName();
        $visaParams["given_name"] =  $application->getMiddleName() . $application->getLastName();
        $visaParams["sex"] =  $application->getGender();;
        $visaParams["passport_number"] =  $application->getPassportNumber();
        $visaParams["document_number"] =  'R' . rand(101112,999999);

        $from_date = new \DateTime();
        $to_date = $from_date->add(new \DateInterval('P1M'));
        $visaParams["valid_from"] =  $from_date->format('d/m/Y');
        $visaParams["valid_until"] =  $to_date->format('d/m/Y');
        $visaParams["date_of_birth"] =  $application->getDateOfBirth()?->format('d/m/Y');
        $visaParams["nationality"] =  $application->getCurrentNationality();

        $this->rootDir = $this->userHelper->getUserUploadDirectory($application->getApplicant());

        $visaParams["userDir"] =  $this->userHelper->getUserUploadDirectory($application->getApplicant());
        $visaParams["photo"] = $this->userHelper->getUserUploadDirectory($application->getApplicant()) . $application->getPhoto();
        $visaParams["evisa"] = '/var/www/html/public/assets/images/evisa_bg.jpg';

        $visaParams["long_number"] = 'V<<<RDC';
        $visaParams["long_number"] .= $visaParams['surname'] . '<<<<<';
        $visaParams["long_number"] .= $visaParams['given_name'] . '<<<<';
        $visaParams["long_number"] .= $visaParams['document_number'] . '<<<<<<<<< ';

        $visaParams["long_number"] .= $visaParams['nationality'];
        $visaParams["long_number"] .= strrev(str_ireplace('/','',$visaParams['date_of_birth'])) . '5M';
        $visaParams["long_number"] .= strrev(str_ireplace('/','', $visaParams['valid_until']));
        $visaParams["long_number"] .= rand(145685815252,999998459847);

        $visaParams['barcode'] = $this->generateBarCode($visaParams["document_number"]);
         return $visaParams;
    }

    function generateBarCode($name)
    {
        $barcodeobj = new TCPDF2DBarcode($name,  "PDF417");
        $barcode = $barcodeobj->getBarcodePngData(250,60);

        $barcodeDirectory = $this->rootDir . "tmp";
        if(!file_exists($barcodeDirectory)) mkdir($barcodeDirectory);
        $tmp_barcode_file = $barcodeDirectory . '/barcode_' . $name. '.png';
        file_put_contents($tmp_barcode_file, $barcode);
        return $tmp_barcode_file;
    }

}
