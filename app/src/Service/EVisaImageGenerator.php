<?php

namespace App\Service;

use Knp\Bundle\SnappyBundle\Snappy\Response\JpegResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EVisaImageGenerator extends ImageRenderer
{
    public function render($userData)
    {
        $fileName = '/var/www/html/var/tmp/' . $userData['document_number'] . '.jpg';
        $html = $this->twig->render('evisa/print.html.twig', ['user'=> $userData ]);
        if(file_exists($userData['barcode'])) unlink($userData['barcode']);

        return new JpegResponse($this->snappy->getOutputFromHtml($html), $fileName);
    }

    public function generate($userData)
    {
        $fileName = $userData['document_number'] . '.jpg';
        $html = $this->twig->render('evisa/print.html.twig', ['user'=> $userData]);
        $output = $this->snappy->getOutputFromHtml($html);

       // file_put_contents($userData['userDir']. $fileName, $output);

       // if(file_exists($userData['barcode'])) unlink($userData['barcode']);

        return new JpegResponse(
            $output,
            $fileName,
            'image/jpg',
            ResponseHeaderBag::DISPOSITION_ATTACHMENT
        );
    }

    public function ajaxGenerate($userData)
    {
        $fileName = $userData['document_number'] . '.jpg';
        $html = $this->twig->render('evisa/print.html.twig', ['user'=> $userData]);
        $output = $this->snappy->getOutputFromHtml($html);

        file_put_contents($userData['userDir']. $fileName, $output);

        if(file_exists($userData['barcode'])) unlink($userData['barcode']);

        return [$fileName, 'image/jpg'];
    }
}