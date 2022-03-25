<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Bpjs\Bridging\Antrol\BridgeAntrol;

class TestController extends Controller
{
    protected $bridging;

    public function __construct()
    {
        $this->bridging = new BridgeAntrol();
    }

    public function getDiagnosa($kode)
    {
        $endpoint = 'referensi/diagnosa/' . $kode;
        return $this->bridging->getRequest($endpoint);
    }

    public function getPoli()
    {
        $endpoint = 'ref/poli';
        $data = $this->bridging->getRequest($endpoint);
        return $data;
    }

    public function postTambahAntrean(Request $request)
    {
        $data = json_encode($request->all());
        $endpoint = 'antrean/add';
        return $this->bridging->postRequest($endpoint, $data);
    }

    public function updateWaktuAntrean(Request $request)
    {
        $data = json_encode($request->all());
        $endpoint = 'antrean/updatewaktu';
        return $this->bridging->postRequest($endpoint, $data);
    }
}
