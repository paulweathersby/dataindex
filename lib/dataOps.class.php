<?php

class dataOps {
    
   
    public $app;

    public function __construct($app)
    {
        $this->app = $app;
    }


    /**
     * @return array<string, mixed>|null
     */
    function processVatResults($jdata)
    {
        if (!is_object($jdata) || !isset($jdata->target) || !is_object($jdata->target)) {
            return null;
        }

        $target  = $jdata->target;
        $address = (isset($target->address) && is_object($target->address)) ? $target->address : null;

        if (!isset($target->vatNumber) || !isset($target->name)) {
            return null;
        }

        $idata = [];
        $idata['vat_number']   = $this->normalizeVatNumber((string) $target->vatNumber);
        $idata['company_name'] = (string) $target->name;
        $idata['address1']     = ($address && isset($address->line1)) ? (string) $address->line1 : '';
        $idata['address2']     = ($address && isset($address->line2)) ? (string) $address->line2 : '';
        $idata['address3']     = ($address && isset($address->line3)) ? (string) $address->line3 : '';
        $idata['address4']     = ($address && isset($address->line4)) ? (string) $address->line4 : '';
        $idata['address5']     = ($address && isset($address->line5)) ? (string) $address->line5 : '';
        $idata['postcode']     = ($address && isset($address->postcode)) ? (string) $address->postcode : '';
        $idata['country_code'] = ($address && isset($address->countryCode)) ? (string) $address->countryCode : '';

        return $idata;
    }


    function saveVatNumber($idata)
    {
        $idata['vat_number'] = $this->normalizeVatNumber((string) ($idata['vat_number'] ?? ''));
        if ($idata['vat_number'] === '') {
            return false;
        }

        $existing = $this->findVatRow($idata['vat_number']);

        if ($existing) {
            $update_data = [
                $idata['company_name'],
                $idata['address1'],
                $idata['address2'],
                $idata['address3'],
                $idata['address4'],
                $idata['address5'],
                $idata['postcode'],
                $idata['country_code'],
                $existing['vat_number'],
            ];

            $sql = 'UPDATE vat_number SET company_name=?, address1=?, address2=?, address3=?, address4=?, address5=?, postcode=?, country_code=? WHERE vat_number=?';
            $query = $this->app->db->prepare($sql);
            $query->execute($update_data);
        } else {
            $insert_data = [
                $idata['vat_number'],
                $idata['company_name'],
                $idata['address1'],
                $idata['address2'],
                $idata['address3'],
                $idata['address4'],
                $idata['address5'],
                $idata['postcode'],
                $idata['country_code'],
            ];

            $sql = 'INSERT INTO vat_number (vat_number, company_name, address1, address2, address3, address4, address5, postcode, country_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $query = $this->app->db->prepare($sql);
            $query->execute($insert_data);
        }

        return true;
    }

    /**
     * Digits-only UK VAT number (no GB prefix) for API calls and lookups.
     */
    function normalizeVatNumber(string $vat_number): string
    {
        $vat = strtoupper(preg_replace('/[^A-Z0-9]/', '', $vat_number) ?? '');
        return preg_replace('/^GB/', '', $vat) ?? '';
    }


    function processOfficerResults($jdata){

        $items = (is_object($jdata) && isset($jdata->items) && is_array($jdata->items))
            ? $jdata->items
            : [];

        $idata = [];

        foreach($items AS $data){

            if (!is_object($data)) {
                continue;
            }

            $output = [];

            $output['name'] = $data->name ?? '';
            if(isset($data->appointed_on)===true){
                $output['appointed_on'] = $data->appointed_on;
            } else {
                $output['appointed_on'] = null;
            }

            if(isset($data->resigned_on)===true){
                $output['resigned_on'] = $data->resigned_on;
            } else {
                $output['resigned_on'] = null;
            }

            if(isset($data->country_of_residence)===true){
                $output['country_of_residence'] = $data->country_of_residence;
            } else {
                $output['country_of_residence'] = '';
            }

            if(isset($data->nationality)===true){
                $output['nationality'] = $data->nationality;
            } else {
                $output['nationality'] = '';
            }

            if(isset($data->occupation)===true){
                $output['occupation'] = $data->occupation;
            } else {
                $output['occupation'] = '';
            }

            if(isset($data->officer_role)===true){
                $output['officer_role'] = $data->officer_role;
            } else {
                $output['officer_role'] = '';
            }

            if(isset($data->date_of_birth)===true){
                if(isset($data->date_of_birth->month)===true){
                    $output['dob_month'] = $data->date_of_birth->month;
                } else {
                    $output['dob_month'] = '';
                }

                if(isset($data->date_of_birth->year)===true){
                    $output['dob_year'] = $data->date_of_birth->year;
                } else {
                    $output['dob_year'] = '';
                }
                
            } else {
                $output['dob_month'] = '';
                $output['dob_year'] = '';            
            }

            $output['registration_number'] = '';
            $output['identification_type'] = '';
            if (isset($data->identification->registration_number)) {
                $output['registration_number'] = $data->identification->registration_number;
            }
            if (isset($data->identification->identification_type)) {
                $output['identification_type'] = $data->identification->identification_type;
            }

            $output['links_appointments'] = '';
            $output['links_self'] = '';
            if (isset($data->links->officer->appointments)) {
                $output['links_appointments'] = $data->links->officer->appointments;
            }
            if (isset($data->links->self)) {
                $output['links_self'] = $data->links->self;
            }

            if(isset($data->address)===true){
                if(isset($data->address->premises)===true){
                    $output['premises'] = $data->address->premises;
                } else {
                    $output['premises'] = '';
                }
                
                if(isset($data->address->address_line_1)===true){
                    $output['address_line_1'] = $data->address->address_line_1;
                } else {
                    $output['address_line_1'] = '';
                }

                if(isset($data->address->postal_code)===true){
                    $output['postal_code'] = $data->address->postal_code;
                } else {
                    $output['postal_code'] = '';
                }

                if(isset($data->address->country)===true){
                    $output['country'] = $data->address->country;
                } else {
                    $output['country'] = '';
                }

                if(isset($data->address->locality)===true){
                    $output['locality'] = $data->address->locality;
                } else {
                    $output['locality'] = '';
                }

                
            } else {
                $output['premises'] = '';
                $output['address_line_1'] = '';    
                $output['postal_code'] = '';
                $output['country'] = '';  
                $output['locality'] = ''; 

            }







            $idata[] = $output;
        }




        return $idata;


    }


    function saveOfficers($idata,$company_number){

        if (!is_array($idata)) {
            return;
        }

        foreach($idata AS $row){

            if (!is_array($row)) {
                continue;
            }

            $prev_query = $this->app->db->prepare(
                "SELECT COUNT(1) AS totalrows FROM officers
                 WHERE company_number = ? AND name = ? AND officer_role = ?"
            );
            $prev_query->execute([$company_number, $row['name'], $row['officer_role']]);
            $data = $prev_query->fetch(PDO::FETCH_ASSOC);


            if(is_array($data) && (int) $data['totalrows'] > 0){
                #update
                $update_data = array($row['name'],$row['registration_number'],$row['identification_type'],$row['country_of_residence'],$row['nationality'],$row['occupation'],$row['premises'],$row['address_line_1'],$row['locality'],$row['postal_code'],$row['country'],$row['links_appointments'],$row['links_self'],$row['appointed_on'],$row['resigned_on'],$row['dob_month'],$row['dob_year'],$company_number,$row['name'],$row['officer_role']);

                $sql = "UPDATE officers SET name=?, registration_number=?, identification_type=?, country_of_residence=?, nationality=?, occupation=?, premises=?, address_line_1=?, locality=?, postal_code=?, country=?, links_appointments=?, links_self=?, appointed_on=?, resigned_on=?, dob_month=?, dob_year=? WHERE company_number=? AND name=? AND officer_role=?";
                
                $query = $this->app->db->prepare($sql);
                $query->execute($update_data);

            } else {
                #insert
                $insert_data = array($company_number,$row['name'],$row['registration_number'],$row['identification_type'],$row['country_of_residence'],$row['nationality'],$row['occupation'],$row['premises'],$row['address_line_1'],$row['locality'],$row['postal_code'],$row['country'],$row['links_appointments'],$row['links_self'],$row['officer_role'],$row['appointed_on'],$row['resigned_on'],$row['dob_month'],$row['dob_year']);

                $sql = "INSERT INTO officers (company_number,name,registration_number,identification_type,country_of_residence,nationality,occupation,premises,address_line_1,locality,postal_code,country,links_appointments,links_self,officer_role,appointed_on,resigned_on,dob_month,dob_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
               
                $query = $this->app->db->prepare($sql);
                $query->execute($insert_data);

            }

        }

    }


    function processFilingResults($fdata){

        $items = (is_object($fdata) && isset($fdata->items) && is_array($fdata->items))
            ? $fdata->items
            : [];

        $idata = [];

        foreach($items AS $data){

            if (!is_object($data)) {
                continue;
            }

            $output = [];

            if(isset($data->action_date)===true){
                $output['action_date'] = $data->action_date;
            } else {
                $output['action_date'] = null;
            }

            if(isset($data->category)===true){
                $output['category'] = $data->category;
            } else {
                $output['category'] = '';
            }

            if(isset($data->date)===true){
                $output['date'] = $data->date;
            } else {
                $output['date'] = null;
            }

            if(isset($data->description)===true){
                $output['description'] = $data->description;
            } else {
                $output['description'] = '';
            }

            if(isset($data->links)===true){

                if(isset($data->links->self)===true){
                    $output['link_self'] = $data->links->self;
                } else {
                    $output['link_self'] = '';
                }

                if(isset($data->links->metadata)===true){
                    $output['link_metadata'] = $data->links->metadata;
                } else {
                    $output['link_metadata'] = '';
                }

            } else {
                $output['link_self'] = '';
                $output['link_metadata'] = '';

            }


            if(isset($data->type)===true){
                $output['type'] = $data->type;
            } else {
                $output['type'] = '';
            }

            if(isset($data->pages)===true){
                $output['pages'] = $data->pages;
            } else {
                $output['pages'] = '';
            }

            if(isset($data->barcode)===true){
                $output['barcode'] = $data->barcode;
            } else {
                $output['barcode'] = '';
            }

            if(isset($data->transaction_id)===true){
                $output['transaction_id'] = $data->transaction_id;
            } else {
                $output['transaction_id'] = '';
            }

            if(isset($data->description_values)===true){
                if(isset($data->description_values->made_up_date)===true){
                    $output['made_up_date'] = $data->description_values->made_up_date;
                } else {
                    $output['made_up_date'] = null;
                }

                if(isset($data->description_values->termination_date)===true){
                    $output['termination_date'] = $data->description_values->termination_date;
                } else {
                    $output['termination_date'] = null;
                }

                if(isset($data->description_values->officer_name)===true){
                    $output['officer_name'] = $data->description_values->officer_name;
                } else {
                    $output['officer_name'] = '';
                }

                if(isset($data->description_values->old_address)===true){
                    $output['old_address'] = $data->description_values->old_address;
                } else {
                    $output['old_address'] = '';
                }

                if(isset($data->description_values->new_address)===true){
                    $output['new_address'] = $data->description_values->new_address;
                } else {
                    $output['new_address'] = '';
                }

                if(isset($data->description_values->appointment_date)===true){
                    $output['appointment_date'] = $data->description_values->appointment_date;
                } else {
                    $output['appointment_date'] = null;
                }

                if(isset($data->description_values->change_date)===true){
                    $output['change_date'] = $data->description_values->change_date;
                } else {
                    $output['change_date'] = null;
                }

            } else {
                $output['made_up_date'] = null;
                $output['termination_date'] = null;
                $output['officer_name'] = '';
                $output['appointment_date'] = null;
                $output['change_date'] = null;
                $output['new_address'] = '';
                $output['old_address'] = '';
            }
            
            $idata[] = $output;

        }


        return $idata;

    }

    /**
     * Officers from Companies House when available; otherwise from the local cache.
     *
     * @return array<int, array<string, mixed>>
     */
    function fetchOfficersForCompany(string $company_number, apiRequest $api): array
    {
        $raw = $api->getDirectors($company_number);

        if ($raw !== null && $raw !== '') {
            $jdata = json_decode($raw);
            if (json_last_error() === JSON_ERROR_NONE) {
                $idata = $this->processOfficerResults($jdata);
                $this->saveOfficers($idata, $company_number);
                return $idata;
            }
        }

        return $this->loadOfficersFromDatabase($company_number);
    }

    /**
     * Filing history from the Companies House API only.
     * The local filings table is not read or written.
     *
     * @return array<int, array<string, mixed>>
     */
    function fetchFilingsForCompany(string $company_number, apiRequest $api): array
    {
        $raw = $api->getFiling($company_number);

        if ($raw === null || $raw === '') {
            return [];
        }

        $fdata = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($fdata)) {
            return [];
        }

        return $this->processFilingResults($fdata);
    }

    /**
     * Load cached VAT data from the database, then try HMRC to refresh.
     * If the API is unavailable or returns an unusable response, the cached row is used.
     *
     * @return array<string, mixed>|null
     */
    function fetchVatRecord(string $vat_number, apiRequest $api): ?array
    {
        $vat_number = $this->normalizeVatNumber($vat_number);
        if ($vat_number === '') {
            return null;
        }

        $cached = $this->loadVatFromDatabase($vat_number);

        $results = $api->vatNumber($vat_number);

        if ($results !== false && $results !== null && $results !== '') {
            $jdata = json_decode((string) $results);
            if (json_last_error() === JSON_ERROR_NONE) {
                $idata = $this->processVatResults($jdata);
                if ($idata !== null && trim((string) ($idata['company_name'] ?? '')) !== '') {
                    $this->saveVatNumber($idata);
                    return $idata;
                }
            }
        }

        return $cached;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    function loadOfficersFromDatabase(string $company_number): array
    {
        $stmt = $this->app->query(
            'SELECT * FROM officers WHERE company_number = :cn ORDER BY appointed_on DESC, name ASC',
            [':cn' => $company_number]
        );

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'name'                  => $row['name']                  ?? '',
                'appointed_on'          => $row['appointed_on']          ?? null,
                'resigned_on'           => $row['resigned_on']           ?? null,
                'country_of_residence'  => $row['country_of_residence']  ?? '',
                'nationality'           => $row['nationality']           ?? '',
                'occupation'            => $row['occupation']            ?? '',
                'officer_role'          => $row['officer_role']          ?? '',
                'dob_month'             => $row['dob_month']             ?? '',
                'dob_year'              => $row['dob_year']              ?? '',
                'registration_number'   => $row['registration_number']   ?? '',
                'identification_type'   => $row['identification_type']   ?? '',
                'links_appointments'    => $row['links_appointments']    ?? '',
                'links_self'            => $row['links_self']            ?? '',
                'premises'              => $row['premises']              ?? '',
                'address_line_1'        => $row['address_line_1']        ?? '',
                'postal_code'           => $row['postal_code']           ?? '',
                'country'               => $row['country']               ?? '',
                'locality'              => $row['locality']              ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    function loadVatFromDatabase(string $vat_number): ?array
    {
        $row = $this->findVatRow($vat_number);

        return $row ? $this->mapVatRow($row) : null;
    }

    /**
     * Find a VAT row regardless of GB prefix / spacing stored in the database.
     *
     * @return array<string, mixed>|null
     */
    function findVatRow(string $vat_number): ?array
    {
        $normalized = $this->normalizeVatNumber($vat_number);
        if ($normalized === '') {
            return null;
        }

        $candidates = array_unique(array_filter([
            $vat_number,
            $normalized,
            'GB' . $normalized,
        ]));

        foreach ($candidates as $candidate) {
            $row = $this->app->query(
                'SELECT * FROM vat_number WHERE vat_number = :vn LIMIT 1',
                [':vn' => $candidate]
            )->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        }

        $row = $this->app->query(
            "SELECT * FROM vat_number
             WHERE REPLACE(REPLACE(UPPER(TRIM(vat_number)), 'GB', ''), ' ', '') = :norm
             LIMIT 1",
            [':norm' => $normalized]
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapVatRow(array $row): array
    {
        return [
            'vat_number'   => $this->normalizeVatNumber((string) ($row['vat_number'] ?? '')),
            'company_name' => $row['company_name'] ?? '',
            'address1'     => $row['address1']     ?? '',
            'address2'     => $row['address2']     ?? '',
            'address3'     => $row['address3']     ?? '',
            'address4'     => $row['address4']     ?? '',
            'address5'     => $row['address5']     ?? '',
            'postcode'     => $row['postcode']     ?? '',
            'country_code' => $row['country_code'] ?? '',
        ];
    }

    function checkCompanyExists($company_number){


        $prev_query = $this->app->db->prepare(
            "SELECT COUNT(1) AS totalrows FROM companies WHERE CompanyNumber = ?"
        );
        $prev_query->execute([$company_number]);
        $data = $prev_query->fetch(PDO::FETCH_ASSOC);

        if(is_array($data) && (int) $data['totalrows'] > 0){
            return true;
        } else {
            return false;
        }



    }


    function cleanName($company){

        $company = (string) $company;
        $company = str_replace(" LTD","",$company);
        $company = str_replace(" LIMITED","",$company);
        $company = str_replace(" LLP","",$company);

        return $company;

    }

    function pcode2String($postcode){

        return strtolower(str_replace(" ","-", (string) $postcode));




    }


    function string2pcode($string){


        return strtoupper(str_replace("-"," ", (string) $string));


    }




    
}