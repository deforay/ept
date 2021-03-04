<?php

class Application_Model_DbTable_Covid19IdentifiedGenes extends Zend_Db_Table_Abstract
{

    protected $_name = 'covid19_identified_genes';
    protected $_primary = 'gene_id';

	public function saveCovid19IdentifiedGenesResults($params) {
        if(count($params['sampleId']) > 0){
            $this->delete(array('map_id' => $params['smid']));
            foreach($params['sampleId'] as $sample){
                foreach($params['geneType'][$sample] as $key=>$gene){
                    $data = array(
                        'map_id'        => $params['smid'],
                        'shipment_id'   => $params['shipmentId'],
                        'sample_id'     => $sample,
                        'gene_id'       => $gene,
                        'ct_value'      => $params['cTValue'][$sample][$key],
                        'remarks'       => $params['remarks'][$sample][$key]
                    );
                    $this->insert($data);
                }
            }
        }
    }

    public function getAllCovid19IdentifiedGeneTypeResponseWise($mapId)
    {
        $sql = $this->select(array('map_id' => $mapId));
		return $this->fetchAll($sql);
    }
}

