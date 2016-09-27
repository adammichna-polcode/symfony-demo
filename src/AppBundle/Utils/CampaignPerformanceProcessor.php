<?php
/**
 * Created by PhpStorm.
 * User: kkowlgi
 * Date: 19/1/16
 * Time: 4:29 AM
 */

namespace AppBundle\Utils;


use AppBundle\Entity\Accounts;
use AppBundle\Entity\Cache\CacheCampaignPerformance;
use AppBundle\Entity\CampaignPerformanceRepository;
use AppBundle\Entity\Campaigns;
use AppBundle\Entity\Adgroups;
use AppBundle\Entity\CampaignPerformance;

class CampaignPerformanceProcessor 
{

    private $em;

    /**
     * @var CampaignPerformanceRepository
     */
    private $campaignPerformanceRepository;

    public function __construct(\Doctrine\ORM\EntityManager $em, CampaignPerformanceRepository $campaignPerformanceRepository) {
        $this->em = $em;
        $this->campaignPerformanceRepository = $campaignPerformanceRepository;
    }


    private $batchSize = 2000;

    public function processReport($fileName, Accounts $accounts) {

        try {
            $time_start = microtime(true);
            $file = new \SplFileObject($fileName, "r");
            $firstLine = $file->fgets();


            $batches = [];
            $dateFormat = 'm/d/Y';
            $shortDateFormat = 'm/d/Y';
            $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

            $q = $this->em->createQuery('delete from AppBundle:CampaignPerformance a where a.accounts = :accounts');
            $q->setParameter('accounts', $accounts);
            $numDeleted = $q->execute();

            $entitiesCreated = 0;
            while ($file && !$file->eof()) {
                $row = $file->fgets();
                $data = explode("\t", $row);
                if (is_array($data) && $data[0]) {
                    $startDate = $data[5];
                    $endDate = $data[6];

                    if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{1,2})\s/', $startDate, $matches)) {
                        $startDate = \DateTime::createFromFormat($shortDateFormat, $matches[1]);
                    } elseif (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{1,4})/', $startDate, $matches)) {
                        $startDate = \DateTime::createFromFormat($dateFormat, $matches[1]);
                    } else {
                        throw new \Exception('Invalid Time format');
                    }

                    if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{1,2}\s)/', $endDate, $matches)) {
                        $endDate = \DateTime::createFromFormat($shortDateFormat, $matches[1]);
                    } elseif (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{1,4})/', $endDate, $matches)) {
                        $endDate = \DateTime::createFromFormat($dateFormat, $matches[1]);
                    } else {
                        throw new \Exception('Invalid Time format');
                    }
                 
                    $campaignEntity = $this->em->getRepository('AppBundle:Campaigns')->findOrAdd($accounts, $data[0], true);
                    $adGroupEntity = $this->em->getRepository('AppBundle:Adgroups')->findOrAdd($campaignEntity, $data[1], true);
                    $skuEntity = $this->em->getRepository('AppBundle:Sku')->findOrAdd($accounts, $data[2], ['ppc' => true], true);

                    # Use proxies which are not full entities to save memory in downloading
                    $skuProxy = $this->em->getReference('AppBundle:Sku', $skuEntity->getId());
                    $adGroupProxy = $this->em->getReference('AppBundle:Adgroups', $adGroupEntity->getId());


                    $campaignPerformanceEntity = new CampaignPerformance();
                    $campaignPerformanceEntity
                        ->setKeyword($data[3])
                        ->setMatchType($data[4])
                        ->setStartDate($startDate)
                        ->setEndDate($endDate)
                        ->setClicks($data[7])
                        ->setImpressions($data[8])
                        ->setAccounts($accounts)
                        ->setAdgroup($adGroupProxy)
                        ->setSku($skuProxy);
                    $batches[] = $campaignPerformanceEntity;

                    $this->em->persist($campaignPerformanceEntity);

                    $entitiesCreated ++;
                    if (($entitiesCreated % $this->batchSize) === 0) {
                        $this->em->flush();
                        echo "Flushing at $entitiesCreated\n";
                        //if ($entitiesCreated > 3000){
                        //    break;
                        //}
                        foreach($batches as $entity) {
                            $this->em->detach($entity);
                        }
                        $batches = [];
                    }

                }
            }


            $this->em->flush();
            echo "Flushed at $entitiesCreated";
            foreach($batches as $entity) {
                $this->em->detach($entity);
            }
            $this->em->flush();

            $time_end = microtime(true);
            $time = $time_end - $time_start;
            echo "Account ".$accounts->getUsername()." took $time secs to load CPR";

            $this->saveCache($accounts->getId());
        } catch(\Exception $e) {
                echo "Failed to load data ".$e->getMessage();
                echo $e->getTraceAsString();
                throw $e;
        }
        return true;
    }

    private function saveCache($accountId){

        foreach ($this->campaignPerformanceRepository->campaignPerformance($accountId) as $item) {
            $ccp = new CacheCampaignPerformance(
                $accountId,
                $item['impressions'],
                $item['clicks'],
                $item['id'],
                $item['baseUnitCost'],
                $item['startDt']
            );
            $this->em->persist($ccp);
        }
        $this->em->flush();
    }

}