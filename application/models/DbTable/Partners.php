<?php

class Application_Model_DbTable_Partners extends Zend_Db_Table_Abstract
{
    protected $_name = 'partners';
    protected $_primary = 'partner_id';

    public function addPartnerDetails($params)
    {
        $partnerId = 0;
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($params['partnerName']) && trim($params['partnerName']) != '') {
            $data = [
                'partner_name' => $params['partnerName'],
                'link' => self::safeLink($params['link'] ?? ''),
                'added_by' => $authNameSpace->admin_id,
                'added_on' => new Zend_Db_Expr('now()'),
                'status' => 'active',
            ];
            $partnerId = $this->insert($data);
            if ($partnerId > 0) {
                $this->savePartnerLogo((int) $partnerId);
                $sortOrder = 1;
                $partnerQuery = $this->getAdapter()->select()->from(['pt' => $this->_name], ['pt.sort_order'])
                    ->order('pt.sort_order DESC');
                $partnerResult = $this->getAdapter()->fetchRow($partnerQuery);
                if ($partnerResult) {
                    $sortOrder = $partnerResult['sort_order'] + 1;
                }
                $this->update(['sort_order' => $sortOrder], ['partner_id = ?' => $partnerId]);
            }
        }
        return $partnerId;
    }

    /**
     * Stores the uploaded logo (form field logoImage) where the home page footer reads it,
     * uploads/partners/logos. Only real images are kept. Without a new upload nothing changes.
     */
    /** Only http and https links are kept, so a partner link can never run script. */
    private static function safeLink($link): string
    {
        $link = trim((string) $link);
        return preg_match('#^https?://#i', $link) ? $link : '';
    }

    private function savePartnerLogo(int $partnerId): void
    {
        $file = $_FILES['logoImage'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return;
        }
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || !isset($types[$info[2]])) {
            return;
        }
        $dir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'partners' . DIRECTORY_SEPARATOR . 'logos';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $name = 'partner-' . $partnerId . '-' . time() . '.' . $types[$info[2]];
        if (move_uploaded_file((string) $file['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name)) {
            $this->update(['logo_image' => $name], ['partner_id = ?' => $partnerId]);
        }
    }

    public function fetchAllPartner($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['partner_name', 'link', 'sort_order', 'DATE_FORMAT(added_on,"%d-%b-%Y")', 'status'];
        $orderColumns = ['partner_name', 'link', 'sort_order', 'added_on', 'status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($orderColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $orderColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['pt' => $this->_name]);

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $link = '';
            $addedDateTime = explode(' ', $aRow['added_on']);
            if (isset($aRow['link']) && trim($aRow['link']) != '') {
                $safeLink = htmlspecialchars((string) $aRow['link'], ENT_QUOTES, 'UTF-8');
                $link = '<a href="' . $safeLink . '" target="_blank" rel="noopener">' . $safeLink . '</a>';
            }
            $row = [];
            $row[] = htmlspecialchars(ucwords((string) $aRow['partner_name']), ENT_QUOTES, 'UTF-8');
            $row[] = $link;
            $row[] = $aRow['sort_order'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($addedDateTime[0]);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/partners/edit/id/' . $aRow['partner_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Edit') . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchPartner($partnerId)
    {
        return $this->fetchRow(['partner_id = ?' => $partnerId]);
    }

    public function updatePartnerDetails($params)
    {
        $partnerId = 0;
        if (isset($params['partnerId']) && trim($params['partnerId']) != '') {
            $partnerId = $params['partnerId'];
            $data = [
                'partner_name' => $params['partnerName'],
                'link' => self::safeLink($params['link'] ?? ''),
                'status' => $params['status'],
            ];
            $this->update($data, ['partner_id = ?' => $partnerId]);
            $this->savePartnerLogo((int) $partnerId);
            if (isset($params['sortOrder']) && trim($params['sortOrder']) != '') {
                $partnerOrderQuery = $this->getAdapter()->select()->from(['pt' => $this->_name], ['pt.partner_id', 'pt.sort_order'])
                    ->order('pt.sort_order ASC');
                $partnerOrderResult = $this->getAdapter()->fetchAll($partnerOrderQuery);
                //Get Min/Max partner order
                $minMaxOrderQuery = $this->getAdapter()->select()->from(['pt' => $this->_name], [new Zend_Db_Expr('min(sort_order) as minSortOrder'), new Zend_Db_Expr('max(sort_order) as maxSortOrder')]);
                $minMaxOrderResult = $this->getAdapter()->fetchRow($minMaxOrderQuery);
                if ($params['sortOrder'] <= $minMaxOrderResult['maxSortOrder']) {
                    $sql = $this->select()->where('partner_id = ? ', $partnerId);
                    $sqlResult = $this->fetchRow($sql);
                    if ($params['sortOrder'] < $sqlResult['sort_order']) {
                        $b = 1;
                        foreach ($partnerOrderResult as $ptOrder) {
                            $bSOrder = $b + 1;
                            if ($ptOrder['sort_order'] >= $params['sortOrder'] && $ptOrder['sort_order'] <= $sqlResult['sort_order']) {
                                if ($ptOrder['partner_id'] == $partnerId) {
                                    $this->update(['sort_order' => $params['sortOrder']], ['partner_id = ?' => $partnerId]);
                                } else {
                                    $this->update(['sort_order' => $bSOrder], ['partner_id = ?' => $ptOrder['partner_id']]);
                                }
                            }
                            $b++;
                        }
                    } elseif ($params['sortOrder'] > $sqlResult['sort_order']) {
                        $b = 1;
                        foreach ($partnerOrderResult as $ptOrder) {
                            $bSOrder = $b - 1;
                            if ($ptOrder['sort_order'] >= $sqlResult['sort_order'] && $ptOrder['sort_order'] <= $params['sortOrder']) {
                                if ($ptOrder['partner_id'] == $partnerId) {
                                    $this->update(['sort_order' => $params['sortOrder']], ['partner_id = ?' => $partnerId]);
                                } else {
                                    $this->update(['sort_order' => $bSOrder], ['partner_id = ?' => $ptOrder['partner_id']]);
                                }
                            }
                            $b++;
                        }
                    }
                }
            }
        }
        return $partnerId;
    }

    public function fetchAllActivePartners()
    {
        $sql = $this->select()
            ->where('status = ? ', 'active')
            ->order('sort_order ASC');
        return $this->fetchAll($sql);
    }
}
