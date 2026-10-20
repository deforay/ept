<?php

class Application_Model_DbTable_ContactUs extends Zend_Db_Table_Abstract
{
    protected $_name = 'contact_us';
    protected $_primary = 'contact_id';

    public function addContact($data)
    {
        return $this->insert($data);
    }

    public function getContact($contactId)
    {
        return $this->fetchRow($this->select()->where('contact_id = ?', $contactId));
    }

    public function getAllContacts($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        // The first five match the table columns for sorting. The rest are searched too.
        $aColumns = ['contacted_on', 'first_name', 'country', 'subject', 'message', 'last_name', 'email', 'participant_id', 'ip_address'];

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
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
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

        $sQuery = $this->getAdapter()->select()->from(['p' => $this->_name]);

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

        // Visitor text: escape everything and defang links, so nothing in a message runs or opens by a click
        $translator = Zend_Registry::get('translate');
        $plain = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $inert = fn ($value) => $plain(Application_Service_Common::defangLinks((string) $value));
        foreach ($rResult as $aRow) {
            $name = trim($aRow['first_name'] . ' ' . $aRow['last_name']);
            $from = '<strong>' . $inert($name) . '</strong>'
                . '<div class="contact-meta">' . $plain($aRow['email']) . '</div>';
            if (trim((string) $aRow['participant_id']) !== '') {
                $from .= '<div class="contact-meta">' . $plain($translator->_('Participant Code/ID')) . ': ' . $inert($aRow['participant_id']) . '</div>';
            }
            $row = [];
            $row[] = $plain(Pt_Commons_DateUtility::humanReadableDateFormat((string) $aRow['contacted_on'], true))
                . '<div class="contact-meta">' . $plain($aRow['ip_address']) . '</div>';
            $row[] = $from;
            $row[] = $inert($aRow['country']);
            $row[] = $inert($aRow['subject'] ?: $aRow['reason']);
            $row[] = '<div class="contact-message">' . nl2br($inert($aRow['message'] ?: $aRow['additional_info'])) . '</div>';
            $row[] = '<a href="/admin/data-managers/add/contact/' . (int) $aRow['contact_id'] . '" class="btn btn-default btn-xs"><i class="icon-plus"></i> ' . $plain($translator->_('Add PT Participant')) . '</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
