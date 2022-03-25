<?php

namespace App\Http\Controllers\WS_BPJS;

use App\Http\Controllers\Controller;
use Bpjs\Bridging\Antrol\BridgeAntrol;
use Illuminate\Http\Request;

class BPJSController extends Controller
{
    protected $bridging;

    public function __construct(){
        $this->bridging = new BridgeAntrol();
    }

    public function postTambahAntrean(Request $request){
        $data = json_encode($request->all());
        $endpoint = 'antrean/add';
        return $this->bridging->postRequest($endpoint, $data);
    }

    public function postUpdateWaktuAntrean(){

    }

    public function postBatalAntrean(){

    }

    public function postListWaktuTaskId(){

    }

    public function postUpdateJadwalDokter(){

    }
}
