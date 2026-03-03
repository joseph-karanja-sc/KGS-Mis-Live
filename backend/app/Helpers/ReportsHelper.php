<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 7/24/2018
 * Time: 12:38 PM
 */

namespace App\Helpers;

use Jaspersoft\Client\Client;
use App\Modules\ProductRegistration\Traits\ProductsRegistrationTrait;
use App\Modules\PremiseRegistration\Traits\PremiseRegistrationTrait;
use App\Modules\GmpApplications\Traits\GmpApplicationsTrait;
use App\Modules\ClinicalTrial\Traits\ClinicalTrialTrait;
use App\Modules\Importexportpermits\Traits\ImportexportpermitsTraits;
use App\Modules\Revenuemanagement\Traits\RevenuemanagementTrait;

class ReportsHelper
{
    public $client = '';
    public $jasper_server_url = '';
    public $jasper_server_username = '';
    public $jasper_server_password = '';

    public function __construct()
    {
        $this->jasper_server_url = Config('constants.jasper.jasper_server_url');
        $this->jasper_server_username = Config('constants.jasper.jasper_server_username');
        $this->jasper_server_password = Config('constants.jasper.jasper_server_password');

        $this->client = new Client(
            $this->jasper_server_url,
            $this->jasper_server_username,
            $this->jasper_server_password
        );
    }

    public function generateJasperReport($input_filename, $output_filename, $mode, $controls)
    {

       $report = $this->client->reportService()->runReport('/reports/Kgs_misv2/' . $input_filename, $mode, null, null, $controls);
    // $report = $this->client->reportService()->runReport('/KGS/' . $input_filename, $mode, null, null, $controls);
        return response($report)
            ->header('Cache-Control', 'no-cache private')
            ->header('Content-Description', 'File Transfer')
            ->header('Content-Type', 'application/pdf')
            ->header('Content-length', strlen($report))
            ->header('Content-Disposition', 'inline; filename=' . $output_filename . '.' . $mode)
            ->header('Content-Transfer-Encoding', 'binary');
    }

}
