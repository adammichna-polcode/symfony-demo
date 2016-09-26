<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityRepository;

class CampaignPerformanceRepository extends EntityRepository {


    /**
     * @param integer $accountId
     * @return array
     */
    public function campaignPerformance($accountId) {

        $qb = $this->defaultQb($accountId);
        $qb->addSelect('a.startDate as startDt')
            ->addGroupBy('startDt')
            ->orderBy('startDt', 'asc');

        $results = $qb->getQuery()
            ->useQueryCache(false)
            ->useResultCache(false)
            ->getResult();
        return $results;

    }

    /**
     * @param integer $accountId
     * @return array
     */
    public function skuStats($accountId) {

        $cacheName = md5($accountId.'skustats');
        $qb = $this->defaultQb($accountId);
        $qb->addSelect('e.sku as sku')
            ->addSelect('e.sku as name')
            ->addSelect('e.img as image')
            ->addSelect('e.asin as asin')
            ->addSelect('e.url as url')
            ->addSelect('e.title as title')
            ->addSelect('e.group as category')
            ->addSelect('e.brand as brand');

        $results = $qb->getQuery()->useResultCache(false, 3600, $cacheName)
            ->getResult();

        return $results;
    }

    /**
     * @param integer $accountId
     * @return array
     */
    public function adgroupStats($accountId) {
        $qb = $this->defaultQb($accountId);
        $qb->addSelect('c.name as adgroup')
            ->addSelect('c.name as name')
            ->addSelect('c.id as adgroupId')
            ->addGroupBy('adgroup');

        $results = $qb->getQuery()->useQueryCache(false)
            ->useResultCache(false)
            ->getResult();


        return $results;
    }

    /**
     * @param integer $accountId
     * @return array
     */
    public function campaignStats($accountId) {
        $qb = $this->defaultQb($accountId);
        $qb->addSelect('d.name as campaign')
            ->addSelect('d.name as name')
            ->addSelect('d.id as campaignId')
            ->addGroupBy('campaignId')
            ->orderBy('campaign');

        $results = $qb->getQuery()->useQueryCache(false)
            ->useResultCache(false)
            ->getResult();

        return $results;
    }

    /**
     * @param integer $accountId
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function defaultQb($accountId) {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->
            select('sum(a.impressions) as impressions')
            ->addSelect('sum(a.clicks) as clicks')
            ->addSelect('e.id as id')
            ->addSelect('e.unitCost as baseUnitCost')
            ->from(CampaignPerformance::class, 'a')
            ->join('a.accounts', 'b')
            ->join('a.adgroup', 'c')
            ->join('c.campaign', 'd')
            ->join('a.sku', 'e')
            ->groupBy('e.sku')
            ->where('a.accounts = :account')
            ->setParameter('account', $accountId);

        return $qb;
    }
}
