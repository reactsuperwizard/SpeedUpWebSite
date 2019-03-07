<?php
session_start();

    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.

    

    ini_set("display_errors", true);
    
 //      ini_set('display_errors', 1);
 //       error_reporting(E_ALL);
    
include("links_functions.php");
$airport_names = array();
$airport_country = array();
$airport_state = array();
$airport_guide = array();

$mainTable = "mightytravels"; // -- also used in importer.php --
$start_time = microtime(true);

$devel = 1;

if ($devel == 1) {
    $deals_table_name = 'mightyDeals';
} else {
    $deals_table_name = 'mightyDeals';
}

$expired_deals_table_name = 'mightyDealsExpired';
$airline_table_name = 'airline';


require_once 'Mobile_Detect.php';
$detect = new Mobile_Detect;

$_GET['expd'] = 1;


/*
$db_tripplaner = Array
(
    'host' => 'localhost',
    'database' => 'tripplanner',
    'user' => 'fare_alerts',
    'password' => '5SaC8HB549nq9t86');
$con = mysqli_connect($db_tripplaner['host'], $db_tripplaner['user'], $db_tripplaner['password'], $db_tripplaner['database']); */


if ($devel == 1) {
    $db_tripplaner = Array
    (
        'host' => '10.138.115.95',
        'database' => 'tripplanner',
        'user' => 'mightytravels-de',
        'password' => 'mnSHj6muRNjgMtS');

    $db_tripplaner2 = Array
    (
        'host' => '10.138.115.95',
        'database' => 'mightytravels-devel',
        'user' => 'mightytravels-de',
        'password' => 'mnSHj6muRNjgMtS');

} else {
    $db_tripplaner = Array
    (
        'host' => '127.0.0.1',
        'database' => 'tripplanner',
        'user' => 'mightytravels',
        'password' => 'xNtBNOwRQxK');

    $db_tripplaner2 = Array
    (
        'host' => '127.0.0.1',
        'database' => 'mightytravels',
        'user' => 'mightytravels',
        'password' => 'xNtBNOwRQxK');
}


$con = mysqli_connect($db_tripplaner['host'], $db_tripplaner['user'], $db_tripplaner['password'], $db_tripplaner['database']);
$con2 = mysqli_connect($db_tripplaner2['host'], $db_tripplaner2['user'], $db_tripplaner2['password'], $db_tripplaner2['database']);


$sql = "SELECT iata,currency
        FROM airport
        WHERE 1";
$result = mysqli_query($con, $sql);


// GET NEARBY AIRPORTS BY IATA
function get_nearby($iata, $distance = 50, $conn)
{
    $sql = 'SELECT * FROM `airport` WHERE `iata` = "' . $iata . '"';
    $airport = mysqli_query($conn, $sql);
    $airport = mysqli_fetch_assoc($airport);


    $R = 3959; //in miles use 6371 for km
    $sql2 = "SELECT iata,
                    name,
                    ( 
                        " . $R . " * acos( cos( radians(" . $airport['lat'] . ") ) * cos( radians(lat) ) * cos( radians(lon) - 
                        radians(" . $airport['lon'] . ")) + sin(radians(" . $airport['lat'] . ")) * sin( radians(lat)))
                    ) AS distance 
            FROM airport WHERE 1 HAVING distance < " . $distance . " ORDER BY distance";

    $query = mysqli_query($conn, $sql2);
    while ($nearby = mysqli_fetch_assoc($query)) {
        #if($nearby['iata'] != $iata) {
        $all[] = $nearby['iata'];
        #}
    }
    return $all;
}


//function for show deal score nice

function show_deal_score($dealScore)
{
    if ($dealScore == 5) {
        return '<span style="color: #000; font-weight: bold;">' . round($dealScore, 2) . '</span>';
    }

    if ($dealScore < 5 && $dealScore >= 2) {
        return '<span style="color: rgba(246,37,48,0.44); font-weight: bold;">' . round($dealScore, 2) . '</span>';
    }

    if ($dealScore < 2) {
        return '<span style="color: rgba(246,37,48,1); font-weight: bold;">' . round($dealScore, 2) . '</span>';
    }

    if ($dealScore >= 8) {
        return '<span style="color: rgb(5,135,3); font-weight: bold;">' . round($dealScore, 2) . '</span>';
    }

    if ($dealScore > 5 && $dealScore < 8) {
        return '<span style="color: rgba(5,135,3,0.44); font-weight: bold;">' . round($dealScore, 2) . '</span>';
    }


}

function show_airport_image($con, $airport)
{
    global $path_url;
    $sql = mysqli_query($con, "SELECT `airport_image` FROM `airport` WHERE LOWER (`iata`)=LOWER ('$airport')");
    if (mysqli_num_rows($sql) == 0) {
        return 0;
    } else {
        $row = mysqli_fetch_array($sql);
        $img_url = $row['airport_image'];
        $img =  $img_url;
    //    $img = $path_url . 'img.php?path=' . $img_url;
        // $img = 0;
        return $img;
    }
}

//some other functions v3.0

    function display_refund_change($con, $airline, $class,$currency,$currency_rate, $locale)
    {
        $html_output = '';
        
        
        $sql = mysqli_query($con, "SELECT * FROM `fare_classes` WHERE `iata`='$airline' AND LOWER(`cabin_class`)=LOWER('$class')");
        if (mysqli_num_rows($sql) > 0) {
            $row = mysqli_fetch_array($sql);
            if ($row['refundable'] == 'Y') {
                $icon_ref = '<img src="ui/refund.png" style="width: 35px;" title="Refundable" />';
            } else {
                $icon_ref = '<img src="ui/norefund.png" style="width: 35px;" title="NON-Refundable" />';
            }
            
            $fee_change1 = $row['change_fee'];
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $fee_change1 = $formatter->formatCurrency($currency_rate * $fee_change1 , $currency);
            
            
            $html_output .= $icon_ref . '<br />Estd. Change fee ' . $fee_change1;
        }
        
        
        return $html_output;
    }


function display_mileage($con, $airline, $class, $miles, $airline_name)
{
    $html_output = '';
    
    $sql = mysqli_query($con, "SELECT * FROM `fare_classes` WHERE `iata`='$airline' AND LOWER(`cabin_class`)=LOWER('$class')");
    if (mysqli_num_rows($sql) > 0) {
        $html_output .= '';
        $row = mysqli_fetch_array($sql);
        $fare_class = $row['fare_class'];

        $sql2 = mysqli_query($con, "SELECT * FROM `earnings_per_fare_class` WHERE `iata_flown`='$airline' AND `fare_class`='$fare_class' ORDER BY `earning_percentage` DESC");
        if (mysqli_num_rows($sql2) > 0) {

            $html_output .= '<br/><b>Please always check the booking site for details. Note we only list earnings per mile flown. Note that Skyteam earnings go by \'Marketing Carrier\', Star Alliance goes by \'Operating Carrier\' and Oneworld goes by \'Marketing Carrier\' (though many exceptions apply)! Watch out for codeshares (we only get the marketing carrier) usually.</b><br/><br>
            <table style="width: 100%; background: #e1e1e1; text-align: center; margin-left: auto;margin-right: auto;padding:0px; margin:0px;line-height: normal;"><tr><td><b><h5>Program name</h5></b></td><td><b><h5>Earning</b></h5></td><td><b><h5>Fare Class</h5></b></td></tr>';
            while ($row2 = mysqli_fetch_array($sql2)) {

                $iata_program = $row2['iata_program'];
                $sql3 = mysqli_query($con, "SELECT p.*, `a`.`name` AS `airline_name` FROM `frequent_flyer_programs` AS `p` JOIN `airline` AS `a` ON (`a`.`iata`=`p`.`iata_program`) WHERE `iata_program`='$iata_program'");
                $row3 = mysqli_fetch_array($sql3);
               
                
                $ep = $row2['earning_percentage'];

                $miles_earned = $miles * ($ep / 100);
                
            // format miles earned nicely
                
                $miles_earned=number_format($miles_earned, 0, '.', '');
                $miles_earned=number_format($miles_earned);
                
                if ($row3['earning_per_mile'] == 'Y') {
                    $html_output .= '<tr style="line-height: normal;"><td style="line-height: normal;"><h5><a href="' . $row3['earn_miles_link'] . '">'.$row3['airline_name'].' '. $row3['full_name'] . '</a></h5></td><td style="line-height: normal;"><h5>' . $miles_earned . ' (' . $ep . '%)</h5></td><td style="line-height: normal;"><h5>' . $fare_class . '</h5></td></tr>';
                }


            }


        }

      //   $sql4 = mysqli_query($con, "SELECT * FROM `earnings_per_spend` WHERE `iata`='$airline'");
      //   if (mysqli_num_rows($sql4) > 0) {
   
        //     $earnings_per_spend=$row3['earnings_per_dollar'];
        //     $earnings_per_spend=$earnings_per_spend*$price;
            
          //  while ($row4 = mysqli_fetch_array($sql4)) {
          //      $iata_program = $row2['iata'];
          //      $sql5 = mysqli_query($con, "SELECT * FROM `frequent_flyer_programs` WHERE `iata_program`='$iata_program'");
          //      $row5 = mysqli_fetch_array($sql5);

          //      $ep = $row4['earnings_per_dollar'];

         //    if ($row5['earning_own_metal_per_dollar'] == 'Y') {
         //            $html_output .= '<tr><td><b>' . $earnings_per_spend . '</b></td><td>' . $ep . ' / $</td><td>' . $fare_class . '</td></tr>';
         //           }
             //  }
       //     }

        $html_output .= '</table>';

    }

    return $html_output;
}
    
    
    
    function display_mileage_teaser($con, $airline, $class, $miles, $airline_name)
    {
        $html_output = '';
        
        $sql = mysqli_query($con, "SELECT * FROM `fare_classes` WHERE `iata`='$airline' AND (`cabin_class`)=('$class')");
        if (mysqli_num_rows($sql) > 0) {
            $html_output .= '';
            $row = mysqli_fetch_array($sql);
            $fare_class = $row['fare_class'];
            
            $sql2 = mysqli_query($con, "SELECT * FROM `earnings_per_fare_class` WHERE `iata_flown`='$airline' AND `fare_class`='$fare_class' ORDER BY `earning_percentage` DESC LIMIT 1");
            if (mysqli_num_rows($sql2) > 0) {
                
                $html_output .= '';
                while ($row2 = mysqli_fetch_array($sql2)) {
                    
                    $iata_program = $row2['iata_program'];
                    $sql3 = mysqli_query($con, "SELECT p.*, `a`.`name` AS `airline_name` FROM `frequent_flyer_programs` AS `p` JOIN `airline` AS `a` ON (`a`.`iata`=`p`.`iata_program`) WHERE `iata_program`='$iata_program'");
                    $row3 = mysqli_fetch_array($sql3);
                    
                    
                    $ep = $row2['earning_percentage'];
                    
                    $miles_earned = $miles * ($ep / 100);
                    
                    // format miles earned nicely
                    
                    $miles_earned=number_format($miles_earned, 0, '.', '');
                    $miles_earned=number_format($miles_earned);
                    
                    if ($row3['earning_per_mile'] == 'Y') {
                        $html_output .= 'This deal should earn up to </i>' . $miles_earned . ' (' . $ep . '%) miles with '.$row3['airline_name'].' '. $row3['full_name'] . ' + Others - Click for Detailed Mileage Earning & Fleet information';
                    }
                    
                    
                }
                
                
            }
            
            //   $sql4 = mysqli_query($con, "SELECT * FROM `earnings_per_spend` WHERE `iata`='$airline'");
            //   if (mysqli_num_rows($sql4) > 0) {
            
            //     $earnings_per_spend=$row3['earnings_per_dollar'];
            //     $earnings_per_spend=$earnings_per_spend*$price;
            
            //  while ($row4 = mysqli_fetch_array($sql4)) {
            //      $iata_program = $row2['iata'];
            //      $sql5 = mysqli_query($con, "SELECT * FROM `frequent_flyer_programs` WHERE `iata_program`='$iata_program'");
            //      $row5 = mysqli_fetch_array($sql5);
            
            //      $ep = $row4['earnings_per_dollar'];
            
            //    if ($row5['earning_own_metal_per_dollar'] == 'Y') {
            //            $html_output .= '<tr><td><b>' . $earnings_per_spend . '</b></td><td>' . $ep . ' / $</td><td>' . $fare_class . '</td></tr>';
            //           }
            //  }
            //     }
            
            $html_output .= '';
            
        }
        
        return $html_output;
    }
    
function get_airline_by_iata($con, $iata)
{
    $sql = mysqli_query($con, "SELECT `name` FROM `airline` WHERE  `iata`='$iata'");
    $row = mysqli_fetch_array($sql);
    return $row['name'];

}
 

function get_mileage_filter($con, $min, $class)
{
    $array_output = array();

    if ($class == "") {
        $class_query = " LOWER(`cabin_class`)=LOWER('economy') OR   LOWER(`cabin_class`)=LOWER('business') OR   LOWER(`cabin_class`)=LOWER('first') OR   LOWER(`cabin_class`)=LOWER('premium')";
    } else {
        $class_query = " LOWER(`cabin_class`)=LOWER('$class')";
    }

    $sql = mysqli_query($con, "SELECT * FROM `fare_classes` WHERE  $class_query");
    if (mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_array($sql);
        $fare_class = $row['fare_class'];

        $sql2 = mysqli_query($con, "SELECT DISTINCT `iata_program` FROM `earnings_per_fare_class` WHERE  `earning_percentage`>='$min' ORDER BY `earning_percentage` DESC");

        if (mysqli_num_rows($sql2) > 0) {


            while ($row2 = mysqli_fetch_array($sql2)) {
                $array_output[] = get_airline_by_iata($con, $row2['iata_program']);
            }

        }


    }

    return $array_output;
}

    function get_mileage_award_redemption_teaser($con, $con2, $class, $airline_flown, $miles, $airport_from, $airport_to)
    {
        
       
        $country_from = get_country_of_airport($con, $airport_from);
        $country_to = get_country_of_airport($con, $airport_to);
   //     $html_output .= $country_from;
  //      $html_output .= $country_to;
    
      
 $sql = mysqli_query($con2, "SELECT id from award_chart_routes WHERE `country_from` = '$country_from' AND `country_to` = '$country_to'");
        
        $row = mysqli_fetch_array($sql);
        $route_id =$row['id'];
       // $html_output .=$route_id;
$sql2 = mysqli_query($con2, "SELECT DISTINCT(iata_program), $class AS miles_needed FROM award_chart_by_airport where route_id=$route_id AND $class>'1' ORDER by miles_needed ASC LIMIT 1");
   
     
        
        while ($row2 = mysqli_fetch_array($sql2)) {
            
             $miles_needed = $row2['miles_needed'];
            
            if ($miles_needed == '0') {
                                        }
         
            else {
                
             $iata_program = $row2['iata_program'];
             
       //      $html_output .=$miles_needed;
       //      $html_output .=$iata_program;
             
            
            
        
        $sql3 = mysqli_query($con2, "SELECT award_chart_own_metal,region_definition, full_name,p.*, `a`.`name` AS `airline_name` FROM `frequent_flyer_programs` AS `p` JOIN `airline` AS `a` ON (`a`.`iata`=`p`.`iata_program`) WHERE `iata_program`='$iata_program'");
        $row3 = mysqli_fetch_array($sql3);
        
        
            
            
              $html_output .= '<p style ="font-size: 0.9em;">Instead of buying a revenue ticket you could redeem as little as ' . number_format($miles_needed) . ' ' . $row3['airline_name'] . ' ' . $row3['full_name'] . ' miles Round Trip for this route in <b>'. ucwords($class) . '</b> plus taxes, fees and surcharges. Click for all redemption options and programs.</p>';
            
        }
   
            
        }
       
        return    $html_output;
    }
    
    
    function get_mileage_award_redemption_full($con, $con2, $class, $airline_flown, $miles, $airport_from, $airport_to)
    {
        
        
        $country_from = get_country_of_airport($con, $airport_from);
        $country_to = get_country_of_airport($con, $airport_to);
        //     $html_output .= $country_from;
        //      $html_output .= $country_to;
        
        
        $sql = mysqli_query($con2, "SELECT id from award_chart_routes WHERE `country_from` = '$country_from' AND `country_to` = '$country_to'");
        
        $row = mysqli_fetch_array($sql);
        $route_id =$row['id'];
         $html_output .=$route_id;
        $sql2 = mysqli_query($con2, "SELECT DISTINCT(iata_program), $class AS miles_needed FROM award_chart_by_airport where route_id=$route_id AND $class>'1' ORDER by miles_needed ASC");
        
        
        $html_output = "<center><table style='width: 85%; text-align: center; margin-left: auto;margin-right: auto;padding:0px; margin:0px;line-height: normal;'><tr><td><b><h5>Program name</h5></b></td><td><b><h5>Miles required for redemption</b></h5></td><td><b><h5>Comments</h5></b></td></tr>";
        
    while ($row2 = mysqli_fetch_array($sql2)) {
                  
                  $miles_needed = $row2['miles_needed'];
                  
                  if ($miles_needed == '0') {
                  }
                  
                  else {
                      
                      $iata_program = $row2['iata_program'];
            
            //     $html_output .=$miles_needed;
            //     $html_output .=$iata_program;
            
            
            $sql3 = mysqli_query($con2, "SELECT award_chart_own_metal,region_definition, full_name,p.*, `a`.`name` AS `airline_name` FROM `frequent_flyer_programs` AS `p` JOIN `airline` AS `a` ON (`a`.`iata`=`p`.`iata_program`) WHERE `iata_program`='$iata_program'");
            $row3 = mysqli_fetch_array($sql3);
            
            
            $html_output .= "<tr style='line-height: normal;'><td style='line-height: normal;'><h5><a target='_new' href='". $row3['award_chart_own_metal'] . "'>" . $row3['airline_name']  ." (".$iata_program.") " . $row3['full_name'] . "</a></h5></td> <td style='line-height: normal;'><a target='_new' href='". $row3['region_definition']. "'>"  . number_format($miles_needed) . " miles required Round Trip (". ucwords($class) . ")</a></td> <td style='line-height: normal;'>Plus taxes, fees and surcharges.</td></tr>" ;
        

    }
              
              
          }
        $html_output .= "</table></center>";
        return    $html_output;
    }
    

    /*
     
        
        $fuel_surcharges_charged_first_elem = 0;
        $airline_name_first_elem = "";
        $i = 0;
        foreach ($final_output as $key => $value) {
            
            $fuel_surcharges = 'minimal';
            
            if ($value['fuel_surcharges_charged'] == 'Y') {
                
                $sql = mysqli_query($con2, "SELECT economy_yq_shorthaul,economy_YQ_av,business_YQ_av FROM `frequent_flyer_programs` where iata_program = '$airline_flown'");
                $row = mysqli_fetch_array($sql);
                
                if ($class == 'economy') {
                    if ($miles <= 2500) {
                       
                        $fuel_surcharges = "$" . number_format($row['economy_yq_shorthaul']);
                    } else {
                        $fuel_surcharges = "$" . number_format($row['economy_YQ_av']);
                    }
                }
            
                else {
                    $fuel_surcharges = "$" . number_format($row['business_YQ_av']);
                
                }
                
                if ($fuel_surcharges == '$') {
                    $fuel_surcharges = 'minimal';
                }
                
                else if ($fuel_surcharges == '$minimal')
                {
                    $fuel_surcharges = 'minimal';
                }
                else {}
            }
            
            $miles_earned = "-";
            $ep = "-";
            $fair_class = "-";
            if ($class == "business") {
                $miles_earned = $value['miles_business'];
            }
            else if ($class == "first") {
                $miles_earned = $value['miles_first'];
            } else {
                $miles_earned = $value['miles_economy'];
            }
            
            //get some add text info from table
            
            $sql = mysqli_query($con2, "SELECT fuel_surcharges_comments,award_chart_own_metal,region_definition FROM `frequent_flyer_programs` where iata_program = '$value[frequent_flyer_programs_iata]'");
            $row = mysqli_fetch_array($sql);
           
            $yq_comments = $row['fuel_surcharges_comments'];
            
            $yq_comments = '';
            
            
            if ($yq_comments != '')
                                    {
            $yq_comments = strlen($yq_comments) > 100 ? substr($yq_comments,0,100)."..." : $yq_comments;
                                        $yq_comments = '</td><tr colspan="3"><div style="font-size:10px;"><b >Note:</b>  ' . $yq_comments . '.</div></td></tr>';
                                        }
            else {}
            
                    
            $html_table_output .= "<tr style='line-height: normal;'><td style='line-height: normal;'><h5><a target='_new' href='". $row['award_chart_own_metal'] . "'>" . $value[airline_name] ." " . $value[frequent_flyer_programs_full_name] . "</a></h5></td> <td style='line-height: normal;'><a target='_new' href='". $row['region_definition']. "'>"  . number_format($miles_earned) . " miles required Round Trip (". ucwords($class) . ")</a></td> <td style='line-height: normal;'>Expect " . $fuel_surcharges . " fuel surcharges". $yq_comments . ".</td></tr>" ;
            
            if ($i == 0) {
                $fuel_surcharges_charged_first_elem = $fuel_surcharges;
                $airline_name_first_elem = $value['airline_name'];
                $ff_first_elem = $value[frequent_flyer_programs_full_name];
            }
            $i++;
        }
        $html_table_output .= "</table></center>";
        
  
        $html_output = '<p style ="font-size: 0.9em;">Instead of buying a revenue ticket you could redeem ' . number_format($miles_trip_first_elem) . ' ' . $airline_name_first_elem . ' ' . $ff_first_elem . ' miles Round Trip ('. ucwords($class) . '). Expect ' . $fuel_surcharges_charged_first_elem. ' fuel surcharges. Click for all redemption options and programs.</p>';
     
        */
    
 
    
    
function get_allow_refunds($con, $allowrefunds, $class)
    {
        $array_output = array();
        
        
        $sql = mysqli_query($con, "SELECT iata FROM `fare_classes` WHERE  LOWER(`cabin_class`)=LOWER('$class') AND refundable='$allowrefunds'");
        if (mysqli_num_rows($sql) > 0) {
            $row = mysqli_fetch_array($sql);
            
            $fare_class = $row['iata'];
            
            
            $array_output[] = get_airline_by_iata($con, $row['iata']);;
        }
        
    }
    
function get_change_fee($con, $max, $class)
    {
        $array_output = array();
        
        
        $sql = mysqli_query($con, "SELECT iata FROM `fare_classes` WHERE  LOWER(`cabin_class`)=LOWER('$class') AND change_fee<='$max'");
        if (mysqli_num_rows($sql) > 0) {
            $row = mysqli_fetch_array($sql);
            
            $fare_class = $row['iata'];
            

        $array_output[] = get_airline_by_iata($con, $row['iata']);;
    }
        
    }
    
    
function get_mileage_filter_program($con, $min, $class)
{
    $array_output = array();

    $sql = mysqli_query($con, "SELECT * FROM `fare_classes` WHERE  LOWER(`cabin_class`)=LOWER('$class')");
    if (mysqli_num_rows($sql) > 0) {
        $row = mysqli_fetch_array($sql);


        $fare_class = $row['fare_class'];

        $sql2 = mysqli_query($con, "SELECT * FROM `earnings_per_fare_class` WHERE  `iata_program`='$min'");
        if (mysqli_num_rows($sql2) > 0) {


            while ($row2 = mysqli_fetch_array($sql2)) {

                $array_output[] = get_airline_by_iata($con, $row2['iata_flown']);;

            }

        }


    }

    return $array_output;
}

function get_country_of_airport($conn, $airport_iata)
{
    $sql = mysqli_query($conn, "SELECT `country` FROM `airport` WHERE LOWER(`iata`)=LOWER('$airport_iata')");
    $row = mysqli_fetch_array($sql);
    return $row['country'];
}

    
function detect_if_flight_international($conn, $conn2, $airport1, $airport2)
{
    $ue = array("Austria", "Belgium", "Bulgaria", "Croatia", "Cyprus", "Czech Republic", "Denmark", "Estonia", "Finland", "France", "Germany", "Greece", "Hungary", "Ireland", "Italy", "Latvia", "Lithuania", "Luxembourg", "Malta", "Netherlands", "Poland", "Portugal", "Romania", "Slovakia", "Slovenia", "Spain", "Sweden", "United Kingdom");

    $country_1 = get_country_of_airport($conn, $airport1);
    $country_2 = get_country_of_airport($conn, $airport2);

    $type = '';

    if (in_array($country_1, $ue) && in_array($country_2, $ue)) {
        $type = 'domestic';
    } else if ($country_1 == $country_2) {
        $type = 'domestic';
    } else {
        $type = 'international';
    }

    return $type;
}

    function get_baggage_data_economy($conn, $airline_iata, $flight_type, $currency, $currency_rate, $locale)
    {
        $return_data = array();
        
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
        
        $sql = mysqli_query($conn, "SELECT * FROM `airline_baggage` WHERE `iata`='$airline_iata'");
        $row = mysqli_fetch_array($sql);
        
        if ($flight_type == "international") {
            
            $return_data['free_bags'] = $row['free_bags_intl'];
            if ($row['first_bag_intl_fee'] != 0) {
                $return_data['first_bag_fee'] = $formatter->formatCurrency($row['first_bag_intl_fee'] * $currency_rate , $currency);
            }
            else
            {$return_data['first_bag_fee'] ='';}
            
            $return_data['free_carry_on'] = $row['free_carry_on_intl_dom'];
            
            if ($row['carry_on_fee'] != 0){
                $return_data['carry_on_fee'] = $formatter->formatCurrency($row['carry_on_fee'] * $currency_rate , $currency);
            }
            else {$return_data['carry_on_fee']='';}
        }
        
        if ($flight_type == "domestic") {
            
            
            $return_data['free_bags'] = $row['free_bags_dom'];
            if ($row['first_bag_intl_dom'] != 0) {
                $return_data['first_bag_fee'] = $formatter->formatCurrency($row['first_bag_intl_dom'] * $currency_rate , $currency);
            }
            else
            {$return_data['first_bag_fee'] ='';}
            
            $return_data['free_carry_on'] = $row['free_carry_on_domestic'];
            
            if ($row['carry_on_fee'] != 0){
                $return_data['carry_on_fee'] = $formatter->formatCurrency($row['carry_on_fee'] * $currency_rate , $currency);
            }
            else {$return_data['carry_on_fee']='';}
        }
        $return_data['policy_link'] = $row['baggage_policy_link'];
        $return_data['comments'] = $row['checked_bags_comments'];
        $return_data['comments_carry_on'] = $row['comments_carry_on'];
        
        return $return_data;
        
    }
    
function get_one_way($conn, $airline_iata, $flight_type)
    {
        $return_data = array();
        
        $sql = mysqli_query($conn, "SELECT * FROM `airline` WHERE `iata`='$airline_iata'");
        $row = mysqli_fetch_array($sql);
        
        if ($flight_type == "international") {
           
            $return_data['works_one_way'] = $row['works_one_way_intl'];
        
        }
        
        if ($flight_type == "domestic") {
            $return_data['works_one_way'] = $row['works_one_way_domestic'];
            
        }
       
        
        return $return_data;
        
    }

function get_baggage_data_prem($conn, $class, $airline_iata, $currency, $currency_rate)
{
    $return_data = array();

    $sql = mysqli_query($conn, "select checked_bags, carry_on, baggage_policy_link from fare_classes c inner join airline_baggage o on c.iata = o.iata where c.iata='$airline_iata' AND c.cabin_class='$class'");
    
    $row = mysqli_fetch_array($sql);

    /*
    if ($currency == "USD") {
        $economy_lowest = "US$" . number_format($row['economy_lowest']);
        $economy_average = "US$" . number_format($row['economy_average']);
    }
    else {
        $economy_lowest = $currency . " " . number_format($row['economy_lowest'] * $currency_rate);
        $economy_average = $currency . " " . number_format($row['economy_average'] * $currency_rate);
    }
    */
    $return_data['free_bags'] = $row['checked_bags'];
    $return_data['first_bag_fee'] = '';
    $return_data['free_carry_on'] = $row['carry_on'];
    $return_data['carry_on_fee'] = '';

    $return_data['policy_link'] = $row['baggage_policy_link'];
    $return_data['comments'] = '';
    $return_data['comments_carry_on'] = '';


    return $return_data;

}


function get_baggage_data($conn, $conn2, $airport1, $airport2, $class, $airline_iata, $currency, $currency_rate)
{

    if (strtolower($class) == 'economy') {
        $flight_type = detect_if_flight_international($conn, $conn2, $airport1, $airport2);
        $baggage_data = get_baggage_data_economy($conn2, $airline_iata, $flight_type, $currency, $currency_rate);
    } else {
        $baggage_data = get_baggage_data_prem($conn2, $class, $airline_iata, $currency, $currency_rate);
    }

    return $baggage_data;
}


function get_one_way_data($conn, $conn2, $airport1, $airport2, $class, $airline_iata)
    {
        
            $flight_type = detect_if_flight_international($conn, $conn2, $airport1, $airport2);
            $one_ways = get_one_way($conn2, $airline_iata, $flight_type);
        
        
        return $one_ways;
    }

    
    function clean($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
        $string = preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
        $string = str_replace('-', ' ', $string);
        return $string;
    }
    
    
    
    function get_savings_per_deal($conn, $from, $to, $cabin_class, $price, $currency, $currency_rate, $locale){
        
        $what_average = $cabin_class.'_average';
        
        //calculate savings
        
        //first get historic price for that deal from DB
        
        
        $query="SELECT $what_average FROM `historic_prices` WHERE `fromCode`='$from' AND `toCode`='$to'";
        $results = mysqli_query($conn, $query);
        if (mysqli_num_rows($results)>0){
            $row=mysqli_fetch_array($results);
            
            
            $average = $row[$what_average];
            
            $normal = $average*1.36;
            
            $per_ticket = $normal - $price;
            
            $savings = 100 -($price /$normal * 100);
            
            $savings = number_format($savings, 0, '.', '');
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $per_ticket  = $formatter->formatCurrency($currency_rate * $per_ticket , $currency);
            
            // change color for savings display
            $colors = '';
            
            if ( $savings < 0 &&  $savings >= 20) {
                $colors ='<span style="color: rgba(246,37,48,0.44); font-weight: bold;">';
            }
            
            if ( $savings < 0) {
                $colors ='<span style="color: rgba(246,37,48,1); font-weight: bold;">';
            }
            
            if ( $savings >= 50) {
                $colors ='<span style="color: rgb(5,135,3); font-weight: bold;">';
            }
            
            if ( $savings > 20 &&  $savings < 50) {
                $colors ='<span style="color: rgba(5,135,3,0.44); font-weight: bold;">';
            }
            
            return ($average >0)?('<br/><b>Save '.$per_ticket.' ('.$colors.$savings.'%</span>)</b><br/> compared to normal prices on this route'):'';
            
        }
        return '';
        
    }
    
    function get_historical_summary($conn,$from,$to,$currency,$currency_rate, $locale){
        
        
        $query="SELECT economy_lowest_airline, economy_occurrence, economy_lowest, economy_average FROM `historic_prices` WHERE `fromCode`='$from' AND `toCode`='$to'";
        $results = mysqli_query($conn, $query);
        if (mysqli_num_rows($results)>0){
            $row=mysqli_fetch_array($results);
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $economy_lowest =  $formatter->formatCurrency($currency_rate * $row['economy_lowest'], $currency);
            $economy_average = $formatter->formatCurrency($currency_rate * $row['economy_average'], $currency);
            
            
            return ($row['economy_occurrence'] && $row['economy_average'] && $row['economy_lowest']>0)?('In <b>Economy</b> the lowest fare we have ever seen was '.$economy_lowest . ' ('.clean($row['economy_lowest_airline']).') and you should <b>buy</b> if it is less than ' . $economy_average .' ('. number_format($row['economy_occurrence']) . ' confidence points).'):'';
        }
        return '';
        
    }
    
    function get_historical_summary_business($conn,$from,$to,$currency,$currency_rate,$locale){
        
        
        $query="SELECT business_lowest_airline, business_occurrence, business_lowest, business_average FROM `historic_prices` WHERE `fromCode`='$from' AND `toCode`='$to'";
        $results = mysqli_query($conn, $query);
        if (mysqli_num_rows($results)>0){
            $row=mysqli_fetch_array($results);
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $business_lowest =  $formatter->formatCurrency($currency_rate * $row['business_lowest'], $currency);
            $business_average = $formatter->formatCurrency($currency_rate * $row['business_average'], $currency);
            
            
            
            return ($row['business_occurrence'] && $row['business_average'] && $row['business_lowest']>0)?('In <b>Business</b> the lowest was '.$business_lowest . ' ('.clean($row['business_lowest_airline']).') and you should <b>buy</b> if it is less than ' . $business_average .' ('. number_format($row['business_occurrence']) . ' confidence points).'):'';
            
        }
        return '';
        
    }
    
    function get_historical_summary_premium_economy($conn,$from,$to,$currency,$currency_rate,$locale){
        
        
        $query="SELECT  premium_economy_lowest_airline, premium_economy_occurrence, premium_economy_lowest, premium_economy_average FROM `historic_prices` WHERE `fromCode`='$from' AND `toCode`='$to'";
        $results = mysqli_query($conn, $query);
        if (mysqli_num_rows($results)>0){
            $row=mysqli_fetch_array($results);
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $premium_economy_lowest =  $formatter->formatCurrency($currency_rate * $row['premium_economy_lowest'], $currency);
            $premium_economy = $formatter->formatCurrency($currency_rate * $row['premium_economy_average'], $currency);
            
            
            
            return ($row['premium_economy_occurrence'] && $row['premium_economy_average'] && $row['premium_economy_lowest']>0)?('In <b>Premium Economy</b> the lowest was '.$premium_economy_lowest . ' ('.clean($row['premium_economy_lowest_airline']).') and you should <b>buy</b> if it is less than ' . $premium_economy_average .' ('. number_format($row['premium_economy_occurrence']) . ' confidence points).'):'';
            
        }
        return '';
        
    }
    
    
    function get_historical_summary_first($conn,$from,$to,$currency,$currency_rate,$locale){
        
        
        $query="SELECT first_lowest_airline,first_occurrence, first_lowest, first_average FROM `historic_prices` WHERE `fromCode`='$from' AND `toCode`='$to'";
        $results = mysqli_query($conn, $query);
        if (mysqli_num_rows($results)>0){
            $row=mysqli_fetch_array($results);
            
            
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            
            $first_lowest =  $formatter->formatCurrency($currency_rate * $row['first_lowest'], $currency);
            $first_average = $formatter->formatCurrency($currency_rate * $row['first_average'], $currency);
            
            
            
            return ($row['first_occurrence'] && $row['first_average'] && $row['first_lowest']>0)?('In <b>First</b> the lowest was '.$first_lowest . ' ('.clean($row['first_lowest_airline']).') and you should <b>buy</b> if it is less than ' . $first_average .' ('. number_format($row['first_occurrence']) . ' confidence points).'):'';
            
        }
        return '';
        
    }


//end some other functions v3.0


$currencies = array();
while ($currency = mysqli_fetch_assoc($result)) {
    $currencies[$currency['iata']] = $currency['currency'];
}


$sql = "SELECT iata,currency,city,country,state,guide_link
        FROM airport
        WHERE 1";
$result_airport = mysqli_query($con, $sql);


while ($currency2 = mysqli_fetch_assoc($result_airport)) {
    $airport_names[$currency2['iata']] = $currency2['city'];
    $airport_country[$currency2['iata']] = strtolower($currency2['country']);
    $airport_state[$currency2['iata']] = strtolower($currency2['state']);
    $airport_guide[$currency2['iata']] = $currency2['guide_link'];
}


/* --- human date show funtion --- */

function human_date($data)
{
    $today = date("Y-m-d H:i:s");
    $date1 = new DateTime($data);
    $date2 = new DateTime($today);
    $interval = $date1->diff($date2);


    if ($date1 > $date2) {
        return 'Today';
    }


    if ($interval->h == 0 && $interval->d == 0) {
        $toreturn = $interval->i . ' minutes ago';
        return $toreturn;
    }

    if ($interval->h <= 6 && $interval->d == 0) {
        $toreturn = $interval->h . ' hours ago';
        return $toreturn;
    }

    if ($interval->h > 6 && $interval->h <= 24 && $interval->d == 0) {
        return 'Today';
    }

    if ($interval->d == 1) {
        return 'Yesterday';
    }

    if ($interval->d >= 2 && $interval->d <= 30) {
        $toreturn = $interval->d . ' days ago';
        return $toreturn;
    }


    return date('j M ', $data);


    //echo "difference " . $interval->y . " years, " . $interval->m." months, ".$interval->d." days ";

    //echo "difference " . $interval->days . " days ";
}


/* ---- set auth to 'guest', 'admin' (not currently used), 'user' ---- */
$auth = 'user';

/* ---- DB connection, check user is logged in if admin/user and defaults vars/functions ---- */


//require_once("incs/mysqlConnect.php");


require_once("incs/defaultVarsFuncs.php"); /* session starts here */


function removeExtraSpaces($txt)
{
    $txt = trim($txt);
    return $txt;
}

function makeCodesArr($codes)
{
    $codes = explode(",", $codes);
    array_pop($codes);
    $newcodes = array();
    foreach ($codes as $c) {
        if ($c != " ") {
            $newcodes[] = removeExtraSpaces($c);
        }

    }

    return $newcodes;
}

function makeCodesSQLready($codes)
{
    $codes = join("','", $codes);
    return $codes;
}


/* end srg extra code */

/* ---- add statistics ------*/

function getVisitorIp()
{
    foreach (array('HTTP_CLIENT_IP',
                 'HTTP_X_FORWARDED_FOR',
                 'HTTP_X_FORWARDED',
                 'HTTP_X_CLUSTER_CLIENT_IP',
                 'HTTP_FORWARDED_FOR',
                 'HTTP_FORWARDED',
                 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $IPaddress) {
                $IPaddress = trim($IPaddress); // Just to be safe

                if (filter_var($IPaddress,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                    !== false
                ) {

                    return $IPaddress;
                }
            }
        }
    }
}
if(!isset($_SESSION['s_user_id'])) {
    header('HTTP/1.0 404 Not Found', true, 404);
    die();
}

$em = $_COOKIE['mighty_user'];
$sql = mysqli_query($con2, "SELECT `id` FROM `text_alerts_customer` WHERE `email`='$em'");
$row = mysqli_fetch_array($sql);
$id_subscriber = $row['id'];
$searchDate = date("Y-m-d H:i:s");



    $sc = mysqli_query($con2, "SELECT locale, currency FROM `text_alerts_customer` WHERE `id`='$id_subscriber'");
    $rc = mysqli_fetch_array($sc);
    
    setcookie("s_currency", $rc['currency'], time() + 3600 * 72, "/");
    $cur_currency = $rc['currency'];
    $locale = $rc['locale'];
    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
    
    
    $sr = mysqli_query($con2, "SELECT `rate` FROM `currencies_rates` WHERE `currency`='$cur_currency'");
    $rr = mysqli_fetch_array($sr);
    $currency_rate = $rr['rate'];


$ip = getVisitorIp();

$string = "";
    foreach ($_GET AS $k => $v) {
        if (strlen($v) > 0 && $k != 'saveThis' && $k != 'start') {
            if (strlen($string) > 0) {
                $string .= "&";
            } else {
                $string .= "?";
            }
            $string .= "$k=$v";
        }
        if (is_array($v)) {
            $string .= "&$k=";
            array_unique($v);
            foreach ($v as $va) {
                $string .= $va . ",";
                
            }
        }
    }
    
 
// save all searches

    $fromformatted = $fromCode;
    $fromformatted = substr($fromformatted,0,3);
    
    $toformatted = $toCode;
    $toformatted = substr($toformatted,0,3);
    

    
mysqli_query($con2, "INSERT INTO `searchStats` (`id`, `id_subscriber`, `searchDate`, `class`, `fromCode`,`fromCountry`, `fromRegion`, `toCode`,`toCountry`, `toRegion`, `airline`, `minMiles`, `maxMiles`, `airline_alliance`, `exclude_lcc`,`skytrax`, `minprice`, `maxprice`, `minCPM`, `maxCPM`, nearby_radius, `mileage_program`, `minimum_miles_earned`, `ip`, `num_results`, `email_active`) VALUES (NULL, '$id_subscriber', '$searchDate', '$class', '$fromformatted', '$fromCountry', '$fromRegion', '$toformatted', '$toCountry', '$toRegion', '$airline', '$minMiles', '$maxMiles', '$airline_alliance', '$_GET[lcc]','$minsky', '$minprice', '$maxprice', '$minCPM', '$maxCPM', '$_GET[nearbyDistance]','$_GET[mileageprogram]', '$_GET[milear]', '$ip', '$num_results','')");
    
$search_id = mysqli_insert_id($con2);

foreach ($_GET as $optkey => $optval) {
    mysqli_query($con2, "UPDATE `searchStats` SET `$optkey`='$optval' WHERE `id`='$search_id'");
}

/* ---- processing of captured forms can go in here, if necessary ---- */

$isHome = 1;

/* prepare codes and cities */

if (strlen($_GET['fromCity']) > 0) {
    $codes_from = "";
    $cities_from = "";
    $cities_input = explode(",", $_GET['fromCity']);
    foreach ($cities_input as $cityi) {
        $c = explode(" - ", $cityi);
        $codes_from .= $c[0] . ',';
        $cities_from .= $c[1] . ',';
    }

    $isHome = 0;

} else {
    $codes_from = "";
    $cities_from = "";
}

if (strlen($_GET['toCity']) > 0) {
    $codes_to = "";
    $cities_to = "";
    $cities_input = explode(",", $_GET['toCity']);
    foreach ($cities_input as $cityi) {
        $c = explode(" - ", $cityi);
        $codes_to .= $c[0] . ',';
        $cities_to .= $c[1] . ',';
    }

    $isHome = 0;

} else {
    $codes_to = "";
    $cities_to = "";
}


/* end prepare codes and cities */

//if (strlen($_GET['nearbyDistance']) > 0) {
    $nearbyDistance = $_GET['nearbyDistance'];
//}

if (strlen($codes_from) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }


    $pre_nearby = makeCodesArr($codes_from);

  //  if ($_GET['shownearby'] == "1") {
        foreach ($pre_nearby as $pn) {
            $nearbyAirports = get_nearby($pn, $nearbyDistance, $con);

            foreach ($nearbyAirports as $nca) {
                if ($nca != $pn) {
                    $codes_from .= $nca . ',';
                }
            }
        }
 //   }

    $isHome = 0;


    $fromcodes = makeCodesSQLready(makeCodesArr($codes_from));


    $fromcode = sqlSecure($codes_from);
    $sfromcode = $codes_from;
//  $criteria .= " fromCode = '$fromcode' ";
    $criteria .= " fromCode IN ('$fromcodes') ";
}


if (strlen($codes_to) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $pre_nearby = makeCodesArr($codes_to);

  //  if ($_GET['shownearbyt'] == "1") {
        foreach ($pre_nearby as $pn) {
            $nearbyAirports = get_nearby($pn, $nearbyDistance, $con);
            foreach ($nearbyAirports as $nca) {
                if ($nca != $pn) {
                    $codes_to .= $nca . ',';
                }
            }
        }
  //  }

    $tocodes = makeCodesSQLready(makeCodesArr($codes_to));

    $tocode = sqlSecure($codes_to);
    $stocode = $codes_to;
//  $criteria .= " toCode = '$tocode' ";
    $criteria .= " toCode IN ('$tocodes') ";

    $isHome = 0;
}


if (strlen($_GET['class']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $class = sqlSecure($_GET['class']);
    $sclass = $_GET['class'];
    $criteria .= " class = '$class' ";
}


if (strlen($_GET['fromRegion']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $fromRegion = sqlSecure($_GET['fromRegion']);
    $sfromregion = $_GET['fromRegion'];
    $criteria .= " fromregion = '$fromRegion' ";

    $isHome = 0;
}
if (strlen($_GET['toRegion']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $toRegion = sqlSecure($_GET['toRegion']);
    $storegion = $_GET['toRegion'];
    $criteria .= " toregion = '$toRegion' ";

    $isHome = 0;
}
/*
if (strlen($_GET['fromCity']) > 0) {
    $fromcity = sqlSecure($_GET['fromCity']);
    $sfromcity = $_GET['fromCity'];
    if (strlen($_GET['fromCode']) < 1) {
        if (strlen($criteria) > 0) {
            $criteria .= " AND ";
        }
        $criteria .= " fromCity LIKE '%$fromcity%' ";
    }
}
if (strlen($_GET['toCity']) > 0) {
    $tocity = sqlSecure($_GET['toCity']);
    $stocity = $_GET['toCity'];
    if (strlen($_GET['toCode']) < 1) {
        if (strlen($criteria) > 0) {
            $criteria .= " AND ";
        }
        $criteria .= " toCity LIKE '%$tocity%' ";
    }
}
*/
if (strlen($_GET['mininbound']) > 0) {
    $smininbound = sqlSecure($_GET['mininbound']);
    $temp = explode("/", sqlSecure($_GET['mininbound']));
    $mininbound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " inbound >= '$mininbound' ";
}
if (strlen($_GET['maxinbound']) > 0) {
    $smaxinbound = sqlSecure($_GET['maxinbound']);
    $temp = explode("/", sqlSecure($_GET['maxinbound']));
    $maxinbound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " inbound <= '$maxinbound' ";

    $isHome = 0;
}
if (strlen($_GET['minoutbound']) > 0) {
    $sminoutbound = sqlSecure($_GET['minoutbound']);
    $temp = explode("/", sqlSecure($_GET['minoutbound']));
    $minoutbound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " outbound >= '$minoutbound' ";

    $isHome = 0;
}
if (strlen($_GET['maxoutbound']) > 0) {
    $smaxoutbound = sqlSecure($_GET['maxoutbound']);
    $temp = explode("/", sqlSecure($_GET['maxoutbound']));
    $maxoutbound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " outbound <= '$maxoutbound' ";
}

if (strlen($_GET['mindealfound']) > 0) {
    /* $smindealfound = sqlSecure($_GET['mindealfound']);
     $temp = explode("/", sqlSecure($_GET['mindealfound']));
     $mindealfound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];*/
    $mindealfound = sqlSecure($_GET['mindealfound']);
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " created >= '$mindealfound' ";

    $isHome = 0;
}
if (strlen($_GET['maxdealfound']) > 0) {
    /*$smaxdealfound = sqlSecure($_GET['maxdealfound']);
    $temp = explode("/", sqlSecure($_GET['maxdealfound']));
    $maxdealfound = $temp[2] . "-" . $temp[0] . "-" . $temp[1];*/
    $maxdealfound = sqlSecure($_GET['maxdealfound']);
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " created <= '$maxdealfound' ";

    $isHome = 0;
}

if (strlen($_GET['airline']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $airline = sqlSecure($_GET['airline']);
    $sairline = $_GET['airline'];
    $criteria .= " airline LIKE '%$airline%' ";

    $isHome = 0;
}

if ($_GET['airline_exclude']) {
    $sairlineexclude = [];
    $airlinecriteria = '';
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    foreach ($_GET['airline_exclude'] as $airline_exclude) {
        if (trim($airline_exclude)) {
            if (!in_array($airline_exclude, $sairlineexclude)) {
                $airlineexclude = sqlSecure($airline_exclude);
                $sairlineexclude[] = $airline_exclude;
                if (strlen($airlinecriteria) > 0) {
                    $airlinecriteria .= " AND ";
                }
                $airlinecriteria .= " airline NOT LIKE '%$airlineexclude%' ";
            }
        }
    }
    $criteria .= $airlinecriteria;

    $isHome = 0;
}

if (strlen($_GET['lcc']) > 0 && $_GET['lcc'] == '1') {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $sql_lcc = mysqli_query($con2, "SELECT `name` FROM `airline` WHERE `alliance`='lcc'");
    $lcc_array = array();
    while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
        $lcc_array[] = $row_lcc['name'];
    }
    $lcc_sql = implode("', '", $lcc_array);

    $criteria .= " airline NOT IN ('$lcc_sql') ";

    $isHome = 0;
}

if (strlen($_GET['airline_alliance']) > 0) {
  
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $airlinealliance = sqlSecure($_GET['airline_alliance']);
    $sairlinealliance = $_GET['airline_alliance'];

    if ($airlinealliance == "lcc") {
        $sql_lcc = mysqli_query($con2, "SELECT `name` FROM `airline` WHERE `alliance`='lcc'");
        $lcc_array = array();
        while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
            $lcc_array[] = $row_lcc['name'];
        }
        $lcc_sql = implode("', '", $lcc_array);

        $criteria .= " airline NOT IN ('$lcc_sql') ";
    } else {
        
        
        $sql_lcc = mysqli_query($con2, "SELECT `name` FROM `airline` WHERE `alliance`='$sairlinealliance'");
        while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
            $lcc_array[] = $row_lcc['name'];
        }
        $lcc_sql = implode("', '", $lcc_array);
        
        $criteria .= " airline IN ('$lcc_sql') ";
        
        //   $left_join = "LEFT JOIN $airline_table_name ON $deals_table_name.airline=$airline_table_name.name";
        //   $left_join_select = ", alliance ";
        //   $criteria .= " alliance = '$airlinealliance' ";
    }

    $isHome = 0;

}

if (strlen($_GET['skytrax']) > 0) {


    $minsky = $_GET['skytrax'];

    if ($minsky > 0) {

        if (strlen($criteria) > 0) {
            $criteria .= " AND ";
        }

        $sql_lcc = mysqli_query($con2, "SELECT `name` FROM `airline` WHERE `skytrax_rating`>='$minsky'");
        $lcc_array = array();
        while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
            $lcc_array[] = $row_lcc['name'];
        }
        $lcc_sql = implode("', '", $lcc_array);

        $criteria .= " airline IN ('$lcc_sql') ";
    }


}

if (strlen($_GET['fromCountry']) > 0) {


    $c = $_GET['fromCountry'];


    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $sql_lcc = mysqli_query($con, "SELECT `iata` FROM `airport` WHERE `country`='$c'");
    $lcc_array = array();
    while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
        $lcc_array[] = $row_lcc['iata'];
    }
    $lcc_sql = implode("', '", $lcc_array);

    $criteria .= " fromCode IN ('$lcc_sql') ";


}

if (strlen($_GET['toCountry']) > 0) {


    $c = $_GET['toCountry'];


    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $sql_lcc = mysqli_query($con, "SELECT `iata` FROM `airport` WHERE `country`='$c'");
    $lcc_array = array();
    while ($row_lcc = mysqli_fetch_array($sql_lcc)) {
        $lcc_array[] = $row_lcc['iata'];
    }
    $lcc_sql = implode("', '", $lcc_array);

    $criteria .= " toCode IN ('$lcc_sql') ";


}


    
if (strlen($_GET['changefee']) > 0) {
        //max change fee input
        
        
        if (strlen($criteria) > 0) {
            $criteria .= " AND ";
        }
        
        $changfee = $_GET['changefee'];
        
        
        $names = get_change_fee($con2, $changefee, $_GET['class']);
        
        
        $lcc_sql = implode("', '", $names);
        
        $criteria .= " airline IN ('$lcc_sql') ";
        
    }
    
    
if (strlen($_GET['allowrefunds']) > 0) {
        //allow refunds?
        
        
        if (strlen($criteria) > 0) {
            $criteria .= " AND ";
        }
        
        $changfee = $_GET['allowrefunds'];
        
        
        $names = get_allow_refunds($con2, $allowrefunds, $_GET['class']);
        
        
        $lcc_sql = implode("', '", $names);
        
        $criteria .= " airline IN ('$lcc_sql') ";
        
    }
    
if (strlen($_GET['milear']) > 0) {
    //get_mileage_filter


    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $minear = $_GET['milear'];


    $names = get_mileage_filter($con2, $minear, $_GET['class']);


    $lcc_sql = implode("', '", $names);

    $criteria .= " airline IN ('$lcc_sql') ";

}

if (strlen($_GET['mileageprogram']) > 0) {
    //get_mileage_filter


    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }

    $minear = $_GET['mileageprogram'];

    if (strlen($_GET['class']) > 0) {
        $names = get_mileage_filter_program($con2, $minear, $_GET['class']);
    } else {
        $names = get_mileage_filter_program($con2, $minear, "economy");
        $names = array_merge($names, get_mileage_filter_program($con2, $minear, "premium"));
        $names = array_merge($names, get_mileage_filter_program($con2, $minear, "business"));
        $names = array_merge($names, get_mileage_filter_program($con2, $minear, "first"));
    }

    $names = array_unique($names);


    $lcc_sql = implode("', '", $names);


    $criteria .= " airline IN ('$lcc_sql') ";

    //echo " airline IN ('$lcc_sql') ";

}


if (strlen($_GET['minMiles']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $minmiles = sqlSecure($_GET['minMiles']);
    $sminmiles = $_GET['minMiles'];
    $criteria .= " milesFlown >= '$minmiles' ";
    $isHome = 0;
}
if (strlen($_GET['maxMiles']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $maxmiles = sqlSecure($_GET['maxMiles']);
    $smaxMiles = $_GET['maxmiles'];
    $criteria .= " milesFlown <= '$maxmiles' ";
    $isHome = 0;
}
if (strlen($_GET['minprice']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $minprice = sqlSecure($_GET['minprice']);
    $sminprice = $_GET['minprice'];
    $criteria .= " price >= '$minprice' ";
    $isHome = 0;
}
if (strlen($_GET['maxprice']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $maxprice = sqlSecure($_GET['maxprice']);
    $smaxprice = $_GET['maxprice'];
    $criteria .= " price <= '$maxprice' ";
    $isHome = 0;
}
if (strlen($_GET['minCPM']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $minCPM = sqlSecure($_GET['minCPM']);
    $smincpm = $_GET['minCPM'];
    $criteria .= " CPM >= '$minCPM' ";
    $isHome = 0;
}
if (strlen($_GET['maxCPM']) > 0) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $maxCPM = sqlSecure($_GET['maxCPM']);
    $smaxcpm = $_GET['maxCPM'];
    $criteria .= " CPM <= '$maxCPM' ";
    $isHome = 0;
}


if ($isHome == 1) {
    if (strlen($criteria) > 0) {
        $criteria .= " AND ";
    }
    $criteria .= " dealscore > '5' ";
}


$string = "";
foreach ($_GET AS $k => $v) {
    if (strlen($v) > 0 && $k != 'saveThis' && $k != 'start') {
        if (strlen($string) > 0) {
            $string .= "&";
        } else {
            $string .= "?";
        }
        $string .= "$k=$v";
    }
    if (is_array($v)) {
        $string .= "&$k=";
        array_unique($v);
        foreach ($v as $va) {
            $string .= $va . ",";
            
        }
    }
}

    
//if ($_GET['saveThis'] == 1) {
//    $s_user_id = $id_subscriber;
//    $s_sql = mysqli_query($con2, "SELECT * FROM `mightyUsersSearch` WHERE `user_id`='$s_user_id'");
//    if (mysqli_num_rows($s_sql) > 0) {
//        mysqli_query($con2, "INSERT INTO `mightyUsersSearch` (`user_id`,`searchString`,`active`) VALUES ('$s_user_id','" . sqlSecure($string) . "','1')");
//    } else {
//        mysqli_query($con2, "INSERT INTO `mightyUsersSearch` (`user_id`,`searchString`,`active`) VALUES ('$s_user_id','" . sqlSecure($string) . "','1')");
//    }


//    $savechecked = "checked";
//}

if (strlen($criteria) > 0) {
    $todaydate = date("Y-m-d H:i:s");
    $criteria .= " AND outbound > '" . $todaydate . "'";
    $criteria = "WHERE " . $criteria;

} else {
    $todaydate = date("Y-m-d H:i:s");
    $criteria .= " outbound > '" . $todaydate . "'";
    $criteria = " WHERE $criteria";
}


$orders_array = array(
    'fco' => array('From Code (a -> Z)', 'fromCode'),
    'fcoD' => array('From Code (Z -> a)', 'fromCode DESC'),
    'fci' => array('From City (a -> Z)', 'fromCity'),
    'fciD' => array('From City (Z -> a)', 'fromCity DESC'),
    'fre' => array('From Region (a -> Z)', 'fromRegion'),
    'freD' => array('From Region (Z -> a)', 'fromRegion DESC'),
    'tco' => array('To Code (a -> Z)', 'toCode'),
    'tcoD' => array('To Code (Z -> a)', 'toCode DESC'),
    'tci' => array('To City (a -> Z)', 'toCity'),
    'tciD' => array('To City (Z -> a)', 'toCity DESC'),
    'tre' => array('To Region (a -> Z)', 'toRegion'),
    'treD' => array('To Region (Z -> a)', 'toRegion DESC'),
    'in' => array('Inbound (oldest first)', 'inbound'),
    'inD' => array('Inbound (most recent first)', 'inbound DESC'),
    'out' => array('Outbound (oldest first)', 'outbound'),
    'outD' => array('Outbound (most recent first)', 'outbound DESC'),
    'air' => array('Airline (a -> Z)', 'airline'),
    'airD' => array('Airline (Z -> a)', 'airline DESC'),
    'pr' => array('Price (lowest first)', 'price DESC'),
    'prD' => array('Price (highest first)', 'price'),
    'cpm' => array('CPM (lowest first)', 'CPM'),
    'cpmD' => array('CPM (highest first)', 'CPM DESC'),

    'dealScore' => array('Deal Score (Better first)', 'dealScore DESC'),
    'dealScoreD' => array('CPM (Worst first)', 'dealScore'),

    'mi' => array('Miles Flown (fewest first)', 'milesFlown'),
    'miD' => array('Miles Flown (most first)', 'milesFlown DESC'),

    'df' => array('Deal found (oldest first)', 'created'),
    'dfD' => array('Deal found (most recent first)', 'created DESC'),

    'lc' => array('Last checked (oldest first)', 'lastCheck'),
    'lcD' => array('Last checked (most recent first)', 'lastCheck DESC'),

);

//'sa'   => array('Saving (biggest first)',   'saving'),
//  'saD'  => array('Saving (smallest first)',  'saving DESC')
foreach ($orders_array AS $k => $v) {
    if ($_GET['order'] == $k) {
        $select = "selected";
    } else {
        $select = "";
    }
    $html_order .= <<<eot
<option value="$k" $select>$v[0]</option>
eot;
}


if (strlen($orders_array[$_GET['order']][1]) > 0) {
    $orderby = $orders_array[$_GET['order']][1];
} else {
    $orderby = "deal_id DESC";
}

$start = 0;
if ($_GET['start'] > 0 && is_numeric($_GET['start'])) {
    $start = sqlSecure($_GET['start']);
}

if ($detect->isMobile()) {
    $maxrows = 10;
} else {
    $maxrows = 24;
}


$query_string = "
  SELECT
    SQL_CALC_FOUND_ROWS
    deal_id, class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
    airline, price, CPM, milesFlown, dealsource, created, lastCheck $left_join_select
  FROM
    $deals_table_name
  $left_join 
  $criteria
  ORDER BY
    $orderby
  LIMIT $start, $maxrows
";


$query_string2 = "
  SELECT
    SQL_CALC_FOUND_ROWS
    deal_id, class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
    airline, price, CPM, milesFlown, dealsource, created, lastCheck, dealScore, if(1 = 1, 'active', 'inactivate') as status $left_join_select
  FROM
    $deals_table_name
  $left_join 
  $criteria
  ORDER BY
    $orderby  
";
$query_string2_count = "
  SELECT
    count(*) as result
  FROM
    $deals_table_name
  $left_join 
  $criteria  
";

$query_string3 = "
  SELECT
    SQL_CALC_FOUND_ROWS
    deal_id, class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
    airline, price, CPM, milesFlown, dealsource, created, lastCheck $left_join_select
  FROM
    $expired_deals_table_name
  $left_join 
  $criteria
  ORDER BY
    $orderby
  LIMIT $start, $maxrows
";


$query_string4 = "
  SELECT
    SQL_CALC_FOUND_ROWS
    deal_id, class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
    airline, price, CPM, milesFlown, dealsource, created, lastCheck, dealScore, if(1 = 1, 'expired', 'inactivate') as status $left_join_select
  FROM
    $expired_deals_table_name
  $left_join 
  $criteria
  ORDER BY
    $orderby
";

$query_string4_count = "
  SELECT
    count(*) as result
  FROM
    $expired_deals_table_name
    $left_join 
    $criteria
  ORDER BY
    $orderby
";

//echo $query_string;


/*
$query_string = "
  SELECT
    minid, $deals_table_name.deal_id, 
    $deals_table_name.class, $deals_table_name.fromCode, $deals_table_name.fromCity, $deals_table_name.toCode, $deals_table_name.toCity, DATE_FORMAT($deals_table_name.inbound, '%d %b %Y') AS inboundText, DATE_FORMAT($deals_table_name.outbound, '%d %b %Y') AS outboundText, $deals_table_name.saving, $deals_table_name.inbound, $deals_table_name.outbound,
    $deals_table_name.airline, $deals_table_name.price, $deals_table_name.CPM, $deals_table_name.milesFlown, $deals_table_name.link, $deals_table_name.created
  FROM
    $deals_table_name
  inner join 
    (select 
        min(deal_id) minid, deal_id, 
        class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
        airline, price, CPM, milesFlown, dealsource, created
     from $deals_table_name 
     group by fromCity, toCity, inbound, outbound
     having count(1) > 1) 
  as duplicates
  on (duplicates.fromCity = $deals_table_name.fromCity
  and duplicates.toCity = $deals_table_name.toCity
  and duplicates.inbound = $deals_table_name.inbound
  and duplicates.outbound = $deals_table_name.outbound
  and duplicates.deal_id <> $deals_table_name.deal_id)
    
    $criteria
  ORDER BY
    $orderby
  LIMIT $start, $maxrows
";*/

/*$query_string = "
SELECT DISTINCT
    SQL_CALC_FOUND_ROWS
    class, fromCode, fromCity, toCode, toCity, DATE_FORMAT(inbound, '%d %b %Y') AS inboundText, DATE_FORMAT(outbound, '%d %b %Y') AS outboundText, saving, inbound, outbound,
    airline, price, CPM, milesFlown, link, created 
FROM 
    $deals_table_name 

    $criteria 
    AND created = 
   (
        SELECT 
        created 
        FROM $deals_table_name 
        GROUP BY fromCity, toCity, inbound, outbound, airline
        ORDER BY created, $orderby
        LIMIT 1
   )
GROUP BY 
    fromCity, toCity, inbound, outbound, airline, created 
LIMIT 
    $start, $maxrows ;
";
*/

// echo '<start>start'.__LINE__.' '.(microtime(true) - $start_time).' </start>';     
$nr2 = 0;


if ($_GET['expd'] == '0') {
    $query4 = mysqli_query($con2, $query_string4);    
    $nr2 = mysqli_num_rows($query4);
    //$query = mysqli_query($con2, $query_string);
    $query2 = mysqli_query($con2, $query_string2);
    //$query3 = mysqli_query($con2, $query_string3);
    $nr1 = mysqli_num_rows($query2);

    if ($nr2 > $nr1) {
        $maxnr = $nr2;
    } else {
        $maxnr = $nr1;
    }
    $all_deals = array();
    for ($num_results = 0;$num_results < $maxnr;) {
        $row = mysqli_fetch_array($query2, MYSQLI_ASSOC);
        if ($row['deal_id'] != "" && $row['price'] != 0) {
            $all_deals[$num_results] = $row;
            $num_results++;
        }
        $row2 = mysqli_fetch_array($query4, MYSQLI_ASSOC);
        if ($row2['deal_id'] != "") {
            $all_deals[$num_results] = $row2;
            $num_results++;
        }
    }
} else {
    $query2 = mysqli_query($con2, $query_string2);
    //$query3 = mysqli_query($con2, $query_string3);
    $nr1 = mysqli_num_rows($query2);
    // echo $query_string2;
    $maxnr = $nr1;
    $all_deals = array();
    // echo 'ok2';
    for ($num_results = 0; $num_results < $maxnr; $num_results ++) {
        if($num_results >= $start && $num_results < $start + $maxrows) {
            $row = mysqli_fetch_array($query2, MYSQLI_ASSOC);    
            $all_deals[$num_results - $start] = $row;
        }                
    }
    // echo '<sql>'.$query_string2_count.'</sql>';
    // $query2_count = mysqli_query($con2, $query_string2_count);
    // $query2_count_res = mysqli_fetch_assoc($query2_count);    
    // $num_results = $query2_count_res['result'];
    // echo 'count = '.$num_results;
    // $start_limit = $start + 1;
    // $sql_query = $query_string2.' LIMIT '.$start_limit.', '.$maxrows;
    // echo $sql_query;
    // $query2 = mysqli_query($con2, $sql_query);
    // //$query3 = mysqli_query($con2, $query_string3);
    // $nr1 = mysqli_num_rows($query2);
    // $all_deals = array();
    // // echo 'ok2';
    // for ($i = 0; $i < $nr1; $i ++) {        
    //     $all_deals[$i] = mysqli_fetch_array($query2, MYSQLI_ASSOC);             
    // }
    // echo '<start>start'.__LINE__.' '.(microtime(true) - $start_time).' </start>';     

}
// echo '<start>start4 '.(microtime(true) - $start_time).' </start>'; 

// echo '<end> nr1='.$nr1.' : nr2='.$nr2.' num_results='.$num_results.' maxnr = '.$maxnr.'</end>'; 
// exit;
//var_dump($query_string);exit();

/*$total_num_deals = mysqli_query($con, "SELECT FOUND_ROWS() AS rows_found");
$result = mysqli_fetch_array($total_num_deals, MYSQLI_ASSOC);*/


//$num_results = $result['rows_found'];
$num_results = count($all_deals);
    
mysqli_query($con2, "UPDATE `searchStats` SET `num_results`='$num_results' WHERE `id`='$search_id'");

    $total_pages = ceil($num_results / $maxrows);
$this_page = ceil(($start + 1) / $maxrows);
$first_page = $this_page - 5;
//die("$this_page - $first_page - $total_pages");
if ($first_page < 1) {
    $first_page = 1;
} else {
    $pagination = '...';
}
$last_page = $this_page + 5;
if ($last_page * $maxrows > $num_results) {
    $last_page = ceil($num_results / $maxrows);
}
while ($first_page <= $last_page) {
    $startrow = ($first_page - 1) * $maxrows;
    if ($first_page == $this_page) {
        $pagination .= <<<eot
[$first_page]&nbsp;&nbsp;
eot;
    } else {
        $pagination .= <<<eot
<a href="javascript:void(0);" onclick="runSearch($startrow);">[$first_page]</a>&nbsp;&nbsp;
eot;
    }
    $first_page++;
}
if ($last_page < $num_results / $maxrows) {
    $pagination .= " ... ";
}

if ($pagination != "" && $this_page != $total_pages) {
    $pagination .= '<a href="javascript:void(0);" onclick="runSearch(' . $this_page * $maxrows . ');"> [Next Page]</a>';
}


if ($num_results < 1) {
    $html_data_rows .= "<tr><td colspan=\"14\" style='font-size: 15px; font-weight: bold;'>Unfortunately, no Mighty Deals match your criteria, please try again.</td></tr>";
}

$remove_dublicats = true;
$previous_data_rows = array();
$dublicat_data_rows = array();

if ($num_results > 0) {
    $html_data_rows .= '<tr><td colspan="13" style="text-align: center; font-size: 17px; font-weight: bold; background-color: whitesmoke;">Found ' . number_format($num_results) . ' fares for your search criteria!</td></tr>';

}

ini_set('display_errors',1);
for ($i = $start; $i < $start + $maxrows; $i++) {
    $r = $all_deals[$i - $start];

    if ($r['toCode'] != "") {
      if (!empty($r['fromCode'])){
          
          $historicalData=get_historical_summary($con2,$r['fromCode'], $r['toCode'],$cur_currency,$currency_rate, $locale);
          $historicalDatabusiness=get_historical_summary_business($con2,$r['fromCode'], $r['toCode'],$cur_currency,$currency_rate,$locale);
          $historicalDatapremiumeconomy=get_historical_summary_premium_economy($con2,$r['fromCode'], $r['toCode'],$cur_currency,$currency_rate,$locale);
          $historicalDatafirst=get_historical_summary_first($con2,$r['fromCode'], $r['toCode'],$cur_currency,$currency_rate,$locale);
          
          $get_savings = get_savings_per_deal($con2,$r['fromCode'], $r['toCode'],$r['class'],$r['price'],$cur_currency,$currency_rate,$locale);
          
      }

        $deal_id = $r['deal_id'];
        $fromCode = $r['fromCode'];
        $fromCity = $r['fromCity'];
        $fromRegion = $r['fromRegion'];
        $toCode = $r['toCode'];
        $toCity = $r['toCity'];
        $toRegion = $r['toRegion'];
        $inbound = $r['inboundText'];
        $outbound = $r['outboundText'];
        $inboundSQL = $r['inbound'];
        $outboundSQL = $r['outbound'];
        $airline = $r['airline'];
        $created = $r['created'];
        $lastCheck = $r['lastCheck'];
        $status = $r['status'];
        
    

        if ($remove_dublicats) {
            $has_dublicat = false;
            if (!empty($previous_data_rows)) {
                foreach ($previous_data_rows as $previous_data_row) {
                    if ($previous_data_row['fromCode'] == $r['fromCode'] &&
                        $previous_data_row['fromCity'] == $r['fromCity'] &&
                        $previous_data_row['toCode'] == $r['toCode'] &&
                        $previous_data_row['toCity'] == $r['toCity'] &&
                        $previous_data_row['inbound'] == $r['inbound'] &&
                        $previous_data_row['airline'] == $r['airline'] &&
                        (
                            $previous_data_row['price'] == $r['price'] ||
                            abs((1 - max(array($previous_data_row['price'], $r['price'])) / min(array($previous_data_row['price'], $r['price']))) * 100) <= 10
                        )
                    ) {
                        $dublicat_data_rows[] = array(
                            'fromCode' => $r['fromCode'],
                            'fromCity' => $r['fromCity'],
                            'toCode' => $r['toCode'],
                            'toCity' => $r['toCity'],
                            'inbound' => $r['inbound'],
                            'outbound' => $r['outbound'],
                            'airline' => $r['airline'],
                            'price' => $r['price']
                        );
                        $has_dublicat = true;
                        break;
                    }
                }
            }
            if ($has_dublicat) {
                continue;
            }
            $previous_data_rows[] = array(
                'fromCode' => $r['fromCode'],
                'fromCity' => $r['fromCity'],
                'toCode' => $r['toCode'],
                'toCity' => $r['toCity'],
                'inbound' => $r['inbound'],
                'outbound' => $r['outbound'],
                'airline' => $r['airline'],
                'price' => $r['price']
            );
        }

        /*
                            // $saving       = $r['saving'] * 100;
         if($saving>0){
           $savingcols = "style=\"color:green\"";
         } else if($saving<0){
           $savingcols = "style=\"color:red\"";
         } else { $savingcols = ""; }
         $saving .= "%";
         */

        if (!isset($currency_rate)) {
            $currency_rate = 1;

        }

     
        
      
        
      
        // Currency conversion for deal price
        
        $price= $r['price'];
        
        $price =  $formatter->formatCurrency($currency_rate * $price, $cur_currency);
        


        //$cpm          = round($r['CPM']);
        $cpm = $r['CPM'];

        $dealScore = $r['dealScore'];

        $miles = number_format($r['milesFlown']);
        $link = $r['dealsource'];
        $class = "";
        if ($r['class'] == 'business') {
            $class = "<b>Business</b>";
            $myclass = 'Business';
            $class_img = 'business.png';
        } else if ($r['class'] == 'economy') {
            $class = "<b>Economy</b>";
            $class_img = 'economy.png';
            $myclass = 'Economy';
        } else if ($r['class'] == 'premium') {
            $class = "<b>Premium Economy</b>";
            $class_img = 'economy.png';
            $myclass = 'Premium Economy';
        } else if ($r['class'] == 'first') {
            $class = "<b>First Class</b>";
            $class_img = 'first.png';
            $myclass = 'First';
        }

        $item = array($fromCode, $fromCity, $toCode, $toCity, $outboundSQL, $inboundSQL, $airline);


        switch ($r['class']) {
            case "business":
                $momondo_tk = 'BIZ';
                $cabinclass = 'Business';
                $cabinkayak = 'business';
                $cabingoogle = 'b';
                $cabinskyscanner = '?cabinclass=business';
                $reviewtype = 'business';
                break;


            case "first":
                $momondo_tk = 'First';
                $cabinclass = 'First';
                $cabinkayak = '';
                $cabingoogle = 'e';
                $reviewtype = 'first';
                $triptype = '0';
                break;
                
            case "premium":
                $momondo_tk = 'premium';
                $cabinclass = 'premium';
                $cabinkayak = '';
                $cabingoogle = 'e';
                $reviewtype = 'premium';
                $triptype = '0';
                break;


            case "economy":
                $momondo_tk = 'ECO';
                $cabinclass = 'Economy';
                $cabinkayak = '';
                $cabingoogle = 'e';
                $reviewtype = 'economy';
                $triptype = '0';
                break;
        }

        /*   if ($r['class'] == 'business') {
               $momondo_tk = 'BIZ';
               $cabinclass = 'Business';
               $cabinkayak = 'business';
               $cabingoogle = 'b';
               $cabinskyscanner = '?cabinclass=business';
               $reviewtype = 'business';
               $triptype = '1';
           } else {
               $momondo_tk = 'ECO';
               $cabinclass = 'Economy';
               $cabinkayak = '';
               $cabingoogle = 'e';
               $reviewtype = 'economy';
               $triptype = '0';

           }*/

        $sql = "SELECT *
            FROM airline
            WHERE name='" . trim($airline) . "'";
        $result = mysqli_query($con, $sql);

        $review = mysqli_fetch_assoc($result);
       
        $fromCity = strlen($fromCity) > 15 ? substr($fromCity,0,15)."..." : $fromCity;

        $toCity = strlen($toCity) > 15 ? substr($toCity,0,15)."..." : $toCity;
        
        $alliance_icon = '';
        switch ($review['alliance']) {
            case "star":
                $alliance_icon = '<br /><img src="ui/star.png" style="width: 65px;margin: auto;" />';
                break;

            case "skyteam":
                $alliance_icon = '<br /><img src="ui/skyteam.png" style="width: 35px;margin: auto;" />';
                break;

            case "oneworld":
                $alliance_icon = '<br /><img src="ui/oneworld.png" style="width: 35px;margin: auto;" />';
                break;

            default:
                $alliance_icon = '';
        }


        switch ($review['skytrax_rating']) {
            case "0":
                $skyimage = '';
                break;

            case "1":
                $skyimage = '<br /><img src="ui/1stars.png" title="1 Star Skytrax Rating" style="width: 40px;" /><br />Skytrax 1 star';
                break;

            case "2":
                $skyimage = '<br /><img src="ui/2stars.png" title="2 Stars Skytrax Rating" style="width: 40px;" /><br />Skytrax 2 stars';
                break;

            case "3":
                $skyimage = '<br /><img src="ui/3stars.png" title="3 Stars Skytrax Rating" style="width: 40px;" /><br />Skytrax 3 stars';
                break;

            case "4":
                $skyimage = '<br /><img src="ui/4stars.png" title="4 Stars Skytrax Rating" style="width: 40px;" /><br />Skytrax 4 stars';
                break;

            case "5":
                $skyimage = '<br /><img src="ui/5stars.png" title="5 Stars Skytrax Rating" style="width: 40px;" /><br />Skytrax 5 stars';
                break;
        }


        $link_airline = '';

        switch ($reviewtype) {
            case "economy":
                if (trim($review['economy']) != '') {
                    $link_airline = $review['economy'];
                }
                break;

            case "business":
                if (trim($review['business']) != '') {
                    $link_airline = $review['business'];
                }
                break;

            case "first":


                if (trim($review['first']) != '') {
                    $link_airline = $review['first'];
                }
                break;
        }

        $airport_image1p = show_airport_image($con, $fromCode);
        $airport_image2p = show_airport_image($con, $toCode);

        $interior_image1 = strtolower($review['iata']);
        $interior_image2 = strtolower($r['class']);
 
    //margin-top: 15px;
        
        $interior_image = '<img style="border:3px solid black;" "width="100" height="70" src="https://mightyfares.mightytravels.com/airline-interiors/' .$interior_image1. '_' .$interior_image2. '_interior.jpg">';

        if ($airport_image1p == "0") {
            $airport_image1 = '';
        } else {

            $airport_image1 = '<img src="' . $airport_image1p . '" title="' . $fromCode . '" alt="' . $fromCode . '" />';
        }

  //      if ($airport_image2p == "0") {
   //         $airport_image2 = '';
  //      } else {

        $airport_image2 = '<img style="border:3px solid black;" src="' . $airport_image2p . '" width="100"  height="70" title="' . $toCode . '" alt="' . $toCode . '" />';
   //     }


        if (false != file_get_contents('https://p.mightytravels.com/airline_logo/' . $review['iata'] . '.png', 0, null, 0, 1) && trim($review['iata']) != '') {
            $airline = '<img class="airline" src="https://p.mightytravels.com/airline_logo/' . $review['iata'] . '.png" style="margin-left: 13px; margin:auto;" />' . $airline . '<br />';

            if ($link_airline != '') $airline .= '<a href="' . $link_airline . '" target="_blank">(Review)</a>';


        }

        $airline .= $skyimage;


        $aiata = trim($review['iata']);


        $sql_fleet = mysqli_query($con2, "SELECT * FROM `airline_fleet` WHERE `iata`='$aiata' AND LOWER(`cabin_type`)=LOWER('$cabinclass')");
        $airline_name = $review['name'];
        //echo "SELECT * FROM `airline_fleet` WHERE `iata`='$aiata' AND LOWER(`cabin_type`)=LOWER('$cabinclass')<br />";

        /* One Way Info */
        
        $one_ways = get_one_way_data($con, $con2, $fromCode, $toCode, $r['class'], $aiata);
        
        $one_way_answer = "";
        if ($one_ways['works_one_way'] == 'Y') {
             $one_way_answer = "<b>Yes</b>";
        } else if ($one_ways['works_one_way'] == 'N') {
             $one_way_answer = "<b>No</b> <br/> <a target=\"_new\" href=\"https://klm.traveldoc.aero/\">Visa Required?</a>";
        }
        
        
        
        $html_one_ways = 'Works One-Way/ Vice Versa? ' . $one_way_answer . '<br/>';
        
        /* baggage code v3 */

        $baggage_data = get_baggage_data($con, $con2, $fromCode, $toCode, $r['class'], $aiata, $cur_currency,$currency_rate);

        $html_baggage = '<div id="baggage' . $deal_id . '"><br/>';

       

        if ($baggage_data['free_bags'] != '') {
            $html_baggage .= '<a href="' . $baggage_data['policy_link'] . '">' . $baggage_data['free_bags'] . 'x FREE Checked Bag(s)</a>';
        }
     
        if ($baggage_data['first_bag_fee'] != '' )  {
            $html_baggage .= '<a href="' . $baggage_data['policy_link'] . '"> <br/>(estd. Bag Fee (beyond allowance): ' . $baggage_data['first_bag_fee'] . ')</a><br/>';
        }
        if ($baggage_data['comments'] != '') {
            $html_baggage .= '<br/><a href="' . $baggage_data['policy_link'] . '">' . $baggage_data['comments'] . '</a><br/>';
        }
        

        if ($baggage_data['free_carry_on'] != '') {
            $html_baggage .= '<br/><a href="' . $baggage_data['policy_link'] . '">' . $baggage_data['free_carry_on'] . 'x FREE Carry-On<a/>';
        }

        if ($baggage_data['carry_on_fee'] != '' ) {
            $html_baggage .= ' (Carry On fee: ' . $baggage_data['carry_on_fee'] . ')<br/>';
        }

        if ($baggage_data['comments_carry_on'] != '') {
            $html_baggage .= '<br/><a href="' . $baggage_data['policy_link'] . '">' . $baggage_data['comments_carry_on'] . '</a><br/>';
        }

     //   if ($baggage_data['policy_link'] != '') {
     //       $html_baggage .= '<p><a href="' . $baggage_data['policy_link'] . '" target="_blank">Please check ' . $airline_name . ' baggage policy link</a></p>';
     //   }


        $html_baggage .= '</div>';

        /* end baggage code v3 */

        $html_fleet = '';
        if (mysqli_num_rows($sql_fleet) > 0) {


            $html_fleet = '<b><div id="fleet' . $deal_id . '">' . $airline_name . ' operates this fleet with the following configuration currently. Please check the booking site for details.<br /><br/></b>
            <table style="width: 100%; text-align: center; margin-left: auto;margin-right: auto;">
<tr>
<td><h5><b>Aircraft Type</h5></b></td>
<td><h5><b>Seat Pitch</h5></b></td>
<td><h5><b>Seat Width</h5></b></td>
<td><h5><b>Seat Type</h5></b></td>
<td><h5><b>Video Type</h5></b></td>
<td><h5><b>Laptop Power</h5></b></td>
<td><h5><b>Power Type</h5></b></td>
<td><h5><b>WIFI</h5></b></td>
</tr>';

            while ($row_fleet = mysqli_fetch_array($sql_fleet)) {
                $html_fleet .= '<tr>
<td><h5>' . $row_fleet['aircraft_type'] . '</h5></td>
<td><h5>' . $row_fleet['seat_pitch'] . '</h5></td>
<td><h5>' . $row_fleet['seat_width'] . '</h5></td>
<td><h5>' . $row_fleet['seat_type'] . '</h5></td>
<td><h5>' . $row_fleet['video_type'] . '</h5></td>
<td><h5>' . $row_fleet['laptop_power'] . '</h5></td>
<td><h5>' . $row_fleet['power_type'] . '</h5></td>
<td><h5>' . $row_fleet['wifi'] . '</h5></td>
</tr>';
            }


            $html_fleet .= '</table>
</div>';
        }


//    if(trim($review[$reviewtype])!=''){
//        $link_airline = $review[$reviewtype];


// $link_airline = $review[$reviewtype];
//        $airline .= '<br>(Review)';
//    }

//    else{
//        $link_airline = 'http://flights.mightytravels.com/en-US/flights/#/result?originplace=' . $item[0] . '&destinationplace=' . $item[2] . '&outbounddate=' . $item[4] . '&inbounddate=' . $item[5] . '&cabinclass='.$cabinclass.'&adults=1&children=0&infants=0';
//    }

        $link_to_do = '';
        if ($airport_guide[$item[2]] != '') {
            $link_to_do = '<br/>(<a href="' . $airport_guide[$item[2]] . '" style="font-size:10px;font-weight:bold;">Things to do ' . $item[3] . '</a>)';
        }

        //$kayak = urlencode('https://www.kayak.com/flights/' . $item[0] . '-' . $item[2] . '/' . $item[4] . '/' . $item[5]) . '/' . $cabinkayak;
        //$kayak = "http://redirect.viglink.com?key=7bb3f84489c1e8bd96325e1a4cf85f29&u=" . $kayak;

        //skyscanner

        // $skyscanner = urlencode("https://www.skyscanner.com/transport/flights/" . $item[0] . "/" . $item[2] . "/" . $item[4] . "/" . $item[5] . "/" . $cabinskyscanner . "");
        //$skyscanner = "https://track.flexlinkspro.com/a.ashx?foid=1051724.2234462&foc=1&fot=9999&fos=1&url=" . $skyscanner;


//    $google_flights = urlencode("https://www.google.com/flights/#search;f=" . $item[0] . ";t=" . $item[2] . ";d=" . $item[2] . ";r=" . $item[4] . ";sc=" . $cabingoogle . "");
        //   $google_flights = "http://redirect.viglink.com?key=7bb3f84489c1e8bd96325e1a4cf85f29&u=" . $google_flights;

        $momondo = urlencode("http://www.momondo.com/flightsearch/?Search=true&TripType=2&SegNo=2&SO0=" . $item[0] . "&SD0=" . $item[2] . "&SDP0=" . date("d-m-Y", strtotime($item[4])) . "&SO1=" . $item[2] . "&SD1=" . $item[0] . "&SDP1=" . date("d-m-Y", strtotime($item[5])) . "&AD=1&TK=" . $momondo_tk . "&DO=false&NA=false");

        $momondo = "http://track.webgains.com/click.html?wgcampaignid=186591&wgprogramid=8613&wgtarget=" . $momondo;

        //$ihg_link="http://go.redirectingat.com?id=71655X1520445&xs=1&url=http://www.ihg.com/destinations/us/en/" . $airport_country[$item[2]] . "-hotels";

        // $ihg_link=str_replace(' ', '-', strtolower($ihg_link));


      $links_flights = getFlightLinksArray($item[0], $item[2], $item[1], $item[3], $item[4], $item[5], $myclass, $item[6], $review['iata'], $id_subscriber, $r['price']);

        // was part of table below
        //    <td  $savingcols><a target="_blank" href="' . $momondo . '">$saving</a></td>

        /*$links_hotels = '

          <div>

          <a target="_blank" href="http://go.redirectingat.com?id=71655X1520445&xs=1&url=http%3A%2F%2Fstarwoodhotels.com/corporate/search/results/detail.html?complexSearchField=' . $item[3] . '">Starwood</b></a>
          <br>
          <a target="_blank" href="http://www.reservetravel.com/v6?siteId=38335&type=city&checkIn=' . $item[4] . '&nights=1&rooms=1&adults=2&city='.$airport_names[$item[2]] .'&propertyclasses=4%20Stars,5%20Stars">MightyRates</a> <br>
              <a target="_blank" href="http://redirect.viglink.com?key=7bb3f84489c1e8bd96325e1a4cf85f29&u='.($airport_country[$item[2]]!='usa' ? urlencode('http://www.marriott.com/hotel-search/' . $airport_names[$item[2]] . '.hotels.'.$airport_country[$item[2]].'.travel/') : 'http://www.marriott.com') .'">Marriott</a> <br>

              <a target="_blank" href="'.$ihg_link.'">IHG</a>';*/

        //format deal source source links, retired for ow
        
        /*
            if ($link =='kayak')  {
                $link = 'Found at <a href="https://www.kayak.com">Kayak.com</a>';
            } else if ($link =='sky')  {
                $link = 'Found at <a href="https://www.skyscanner.com">Skyscanner.com</a>';
            }
            else if ($link =='AFWD')  {
                $link = 'Found at <a href="https://www.momondo.com">Momondo.com</a>';
            }
         */
        
        $link = '<b>Book this deal now</b><br/>(click the blue triangle for all booking links)<b>:</b>';

        $links_hotels = getHotelLinksArray($item[3], $airport_country[$item[2]], $item[4]);

//        $airline .= '<button class="btn btn-xs btn-primary button1 exl" data-airline="' . htmlentities($r['airline']) . '" onclick="exclude(this);" style="margin-top: 5px;">Exclude</button>';

        if ($lastCheck == "0000-00-00 00:00:00") {
            $lastCheck = $created;
        }

        $lastCheck_human = human_date($lastCheck);
        $created_human = human_date($created);

        $sql_lcc2 = mysqli_query($con2, "SELECT `name` FROM `airline` WHERE `alliance`='lcc'");
        $lcc_array2 = array();
        while ($row_lcc2 = mysqli_fetch_array($sql_lcc2)) {
            $lcc_array2[] = $row_lcc2['name'];
        }

        if (!in_array($r['airline'], $lcc_array2)) {
            $mainlink_f = "Momondo.com";
            $mainlink_h = "Booking.com";

        } else {
            $mainlink_f = "Wego";
            $mainlink_h = "Booking.com";
        }

        $part_flights = '';


        foreach ($links_flights as $fl) {
            if ($fl['name'] == $mainlink_f) {
                $mainlink_ff = '<a href="' . $fl['url'] . '" target="_blank" style="float: left;">' . $fl['name'] . '</a>';
            }

            $part_flights .= ' <li><a href="' . $fl['url'] . '" target="_blank">' . $fl['name'] . '</a></li>';
        }

        $final_flights_links = '<div class="mydropdown" style="background-color: #ccc;"><ul><li class="clickSlide">' . $mainlink_ff . ' <span onclick="showDropdownLinks(\'#flightslinks' . $deal_id . '\');" style="float: right;">&#x25BC;</span></a>
            <ul id="flightslinks' . $deal_id . '" style="display: none;">
               ' . $part_flights . '
            </ul>
            </li>
        </ul></div>';

        $part_hotels = '';


        foreach ($links_hotels as $fl) {
            if ($fl['name'] == $mainlink_h) {
                $mainlink_hh = '<a href="' . $fl['url'] . '" target="_blank" style="float: left;">' . $fl['name'] . '</a>';
            }

            $part_hotels .= ' <li><a href="' . $fl['url'] . '" target="_blank">' . $fl['name'] . '</a></li>';
        }

        $final_hotels_links = '<div class="mydropdown" style="background-color: #ccc;"><ul><li class="clickSlide">' . $mainlink_hh . ' <span onclick="showDropdownLinks(\'#hotellinks' . $deal_id . '\');" style="float: right;">&#x25BC;</span></a>
            <ul id="hotellinks' . $deal_id . '" style="display: none;">
               ' . $part_hotels . '
            </ul>
            </li>
        </ul></div>';

        $dealScore_div = show_deal_score($dealScore);


                if ($i % 2 == 0) {
                    $backcoltr = '#f2f2f2';
                } else {
                    $backcoltr = '#fff';
                }
                
                $dm = display_mileage($con2, $review['iata'], $cabinclass, $r['milesFlown'], $airline_name);
                $dmteaser = display_mileage_teaser($con2, $review['iata'], $cabinclass, $r['milesFlown'], $airline_name);
                  $ref_change = display_refund_change($con2, $review['iata'], $cabinclass,$cur_currency,$currency_rate,$locale);
                $miles_clean = intval(preg_replace('/[^\d.]/', '', $miles));
                
           
                
           
                $award_rates_teaser = get_mileage_award_redemption_teaser($con, $con2, strtolower($cabinclass), $review['iata'], $miles_clean, $fromCode, $toCode);
                $award_rates_full = get_mileage_award_redemption_full($con, $con2, strtolower($cabinclass), $review['iata'], $miles_clean, $fromCode, $toCode);

                
                
       // Outputs the Input variables for Award Rates
        //        $award_var = strtolower($cabinclass) . $review['iata'] . $miles_clean . $fromCode. $toCode;
                
                if ($historicalData!=''){
                  $html_data_rows.=<<<eot
        <tr style="background-color: $backcoltr;">
                <td colspan="12" style="text-align: center;">
               
                $historicalData
                $historicalDatabusiness
                $historicalDatapremiumeconomy
                $historicalDatafirst
                
                </td>
                </tr>
eot;
                }
                $html_data_rows .= <<<eot

<tr id="dealnr-$deal_id" style="width: 100%; background-color: $backcoltr;">

<td>$class <br /><img src="ui/$class_img" alt="$myclass" title="$myclass" style="max-width:30px;" /></td>
   
                <td style="text-align: center;"><b>$fromCity <br/></b>($fromCode)<br/> $interior_image </td>
                
    <td style="text-align: center;"><b>$toCity <br/></b>($toCode) <br />$airport_image2 $link_to_do <br /><b>Hotels:</b><br />$final_hotels_links </td>
        <td><b>$outbound</b><br/> <br/><p style="color:red;">This is just one random sample date.</span></td>
        <td><b>$inbound </b><br/> <br/> Use the boking link to find more dates.</p></td>
    <td style="text-align: center;">$airline $alliance_icon</td>
     <td style="text-align: center;"><span style="font-size:15px; font-weight: bold; ">$price</span><br /><b>Round Trip</b> <br/> $get_savings <br />$ref_change</td>
      <td style="font-size:12px; text-align: center;"><b>$created_human<b/><br /><br /><a style="cursor: pointer;color:red;" onclick="reportDeal('$deal_id');"><img src="ui/report-deal.png" alt="Report Deal as expired" title="Report Deal as expired" style="max-width:20px;" /></a> <a style="cursor: pointer;color:green;" onclick="coolDeal('$deal_id');"><img src="ui/cool-deal.png" alt="Cool Deal!" title="Cool Deal!" style="max-width:20px;" /></a> <a href="mailto:premium@mightytravels.com" style="cursor: pointer;color:green;"><img src="ui/question.png" alt="Have a question?" title="Have a question?" style="max-width:23px;" /></a></td>
   <!-- <td style="font-size:12px;"><b>Source $link<b/></td>-->
    <td>$miles</td>
    <td>$cpm</td>
 
    <td>$dealScore_div</td>
                
                <td>$link<br/>
                $final_flights_links <br/>$html_baggage <br/>  $html_one_ways </td>
    </tr>
   <!--

<tr style="background-color: $backcoltr;">
<td colspan="12" style="text-align: center;">/td>
</tr>
          -->
eot;

      //   if($reviewtype=='business'){
$html_data_rows .= <<<eot
                <tr style="background-color: $backcoltr;">
                <td colspan="12" style="text-align: center;">
          $award_rates
                        <h5 font onclick="(function(dealID){var el=document.querySelector('#fleet_deal_'+dealID),display=el.style.display; if (display=='block'){ el.style.display='none';} else {el.style.display='block';}})($deal_id);">$dmteaser <br/><br/>  $award_rates_teaser
                   <div id="fleet_deal_$deal_id" style="display:none;">
                <h3>Detailed Mileage Earning Options</h3>
                $dm
                
                <p>
                <h3>Detailed Fleet Information</h3>
             $html_fleet
                <p>
                <h3>Miles Redemption Options</h3>
                $award_rates_full
                </div>
                
                
                
                </td>
                </tr>
            </h5>

eot;
//}
            }
        }

 //   }


//} // -- while --

//  --- Send some headers to keep the user's browser from caching the response.
//$html_data_rows=    preg_replace('/[^\\x0009\\x000A\\x000D\\x0020-\\xD7FF\\xE000-\\xFFFD]/', '', $html_data_rows);

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-Type: text/xml; charset=utf-8");
header("Content-Length: " . strlen($html_data_rows . $pagination));
header("Connection: close");

PRINT<<<EOT
<ROOT>
<HTML><![CDATA[$html_data_rows<tr><td colspan="14" align="right"><span style="font-size:150%;">$pagination</span></td></tr>]]></HTML>
</ROOT>
EOT;

?>
