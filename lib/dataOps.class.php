<?php

class dataOps {
    
   
    public $app;

    public function __construct($app)
    {
        $this->app = $app;
    }


    function processVatResults($jdata){


        $idata['vat_number'] = $jdata->target->vatNumber;
        $idata['company_name'] = $jdata->target->name;
        if(isset($jdata->target->address->line1)===true){
            $idata['address1'] = $jdata->target->address->line1;
        } else {
            $idata['address1'] = '';

        }
        if(isset($jdata->target->address->line2)===true){
            $idata['address2'] = $jdata->target->address->line2;
        } else {
            $idata['address2'] = '';

        }
        if(isset($jdata->target->address->line3)===true){
            $idata['address3'] = $jdata->target->address->line3;
        } else {
            $idata['address3'] = '';

        }
        if(isset($jdata->target->address->line4)===true){
            $idata['address4'] = $jdata->target->address->line4;
        } else {
            $idata['address4'] = '';

        }
        if(isset($jdata->target->address->line5)===true){
            $idata['address5'] = $jdata->target->address->line5;
        } else {
            $idata['address5'] = '';

        }
        $idata['postcode'] = $jdata->target->address->postcode;
        $idata['country_code'] = $jdata->target->address->countryCode;

        return $idata;



    }


    function saveVatNumber($idata){


        $prev_query = $this->app->db->prepare(
            "SELECT COUNT(1) AS totalrows FROM vat_number WHERE vat_number = ?"
        );
        $prev_query->execute([$idata['vat_number']]);
        $data = $prev_query->fetch(PDO::FETCH_ASSOC);

       

        if($data['totalrows'] > 0){
            #update
            $update_data = array($idata['company_name'],$idata['address1'],$idata['address2'],$idata['address3'],$idata['address4'],$idata['address5'],$idata['postcode'],$idata['country_code'],$idata['vat_number']);

            $sql = "UPDATE vat_number SET company_name=?, address1=?, address2=?, address3=?, address4=?, address5=?, postcode=?, country_code=? WHERE vat_number=?";


            $query = $this->app->db->prepare($sql);
            $query->execute($update_data);

        } else {
            #insert
            $insert_data = array($idata['vat_number'],$idata['company_name'],$idata['address1'],$idata['address2'],$idata['address3'],$idata['address4'],$idata['address5'],$idata['postcode'],$idata['country_code']);

            $sql = "INSERT INTO vat_number (vat_number, company_name, address1, address2, address3, address4, address5, postcode, country_code) VALUES (?, ?, ?, ? ,?, ?, ?, ?, ?)";
            $query = $this->app->db->prepare($sql);
            $query->execute($insert_data);



        }

        return true;

    }


    function processOfficerResults($jdata){

        if (isset($jdata->items)) {
            $items = $jdata->items;
            // Further processing of $items
        } else {
            // Handle the case where $items does not exist
            $items = [];
        }
        

        $idata = [];

        foreach($items AS $data){

            $output = [];

            $output['name'] = $data->name;
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

            if(isset($data->identification)===true){
                $output['registration_number'] = $data->identification->registration_number;
                $output['identification_type'] = $data->identification->identification_type;
                
            } else {
                $output['registration_number'] = '';
                $output['identification_type'] = '';            
            }

            if(isset($data->links)===true){
                $output['links_appointments'] = $data->links->officer->appointments;
                $output['links_self'] = $data->links->self;
                
            } else {
                $output['links_appointments'] = '';
                $output['links_self'] = '';            
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

        foreach($idata AS $row){

            $prev_query = $this->app->db->prepare(
                "SELECT COUNT(1) AS totalrows FROM officers
                 WHERE company_number = ? AND name = ? AND officer_role = ?"
            );
            $prev_query->execute([$company_number, $row['name'], $row['officer_role']]);
            $data = $prev_query->fetch(PDO::FETCH_ASSOC);


            if($data['totalrows'] > 0){
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

        if (isset($fdata->items)) {
            $items = $fdata->items;
            // Further processing of $items
        } else {
            // Handle the case where $items does not exist
            $items = [];
        }

        $idata = [];

        foreach($items AS $data){

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

    function saveFilings($idata,$company_number){

        foreach($idata AS $row){

            $prev_query = $this->app->db->prepare(
                "SELECT COUNT(1) AS totalrows FROM filings
                 WHERE company_number = ? AND barcode = ?"
            );
            $prev_query->execute([$company_number, $row['barcode']]);
            $data = $prev_query->fetch(PDO::FETCH_ASSOC);


            if($data['totalrows'] == 0){


                #insert
                $insert_data = array($company_number,$row['action_date'],$row['category'],$row['date'],$row['description'],$row['link_self'],$row['link_metadata'],$row['type'],$row['pages'],$row['barcode'],$row['transaction_id'],$row['made_up_date'],$row['termination_date'],$row['officer_name'],$row['appointment_date'],$row['change_date'],$row['old_address'],$row['new_address']);

                $sql = "INSERT INTO filings (company_number,action_date,category,date,description,link_self,link_metadata,`type`,pages,barcode,transaction_id,made_up_date,termination_date,officer_name,appointment_date,change_date,old_address,new_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
               
                $query = $this->app->db->prepare($sql);
                $query->execute($insert_data);


            }

        }

    }


    function checkCompanyExists($company_number){


        $prev_query = $this->app->db->prepare(
            "SELECT COUNT(1) AS totalrows FROM companies WHERE CompanyNumber = ?"
        );
        $prev_query->execute([$company_number]);
        $data = $prev_query->fetch(PDO::FETCH_ASSOC);

        if($data['totalrows'] > 0){
            return true;
        } else {
            return false;
        }



    }


    function cleanName($company){

        $company = str_replace(" LTD","",$company);
        $company = str_replace(" LIMITED","",$company);
        $company = str_replace(" LLP","",$company);

        return $company;

    }

    function pcode2String($postcode){

        return strtolower(str_replace(" ","-",$postcode));




    }


    function string2pcode($string){


        return strtoupper(str_replace("-"," ",$string));


    }




    
}