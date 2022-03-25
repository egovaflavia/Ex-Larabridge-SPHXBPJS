<?php

namespace App\Http\Controllers\WS_RS;

use App\Http\Controllers\Controller;
use App\Models\WS_RS\Antrian;
use App\Models\WS_RS\JadwalOperasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SPHController extends Controller
{
    /** ============================ Validasi Section =========================================== */
    private function getJadwalOperasiVal($input){
        $rule = [
            'tanggalawal' => 'required|date',
            'tanggalakhir' => 'required|date',
        ];

        $message = [
            'date' => 'Format tanggal salah (YYYY-MM-DD)',
            'required' => 'Isian : attribut harus terisi',
        ];

        $validate = Validator::make($input,$rule,$message);
        if ($validate->fails()) {
            $error = $validate->errors()->first();
            return $error;
        }else{
            return "Ok";
        }
    }

    private function getBatalVal($input){
        $rule = [
            'kodebooking' => 'required|string',
        ];

        $message = [
            'required' => 'Isian : attribut harus terisi',
        ];

        $validate = Validator::make($input,$rule,$message);
        if ($validate->fails()) {
            $error = $validate->errors()->first();
            return $error;
        }else{
            return "Ok";
        }
    }

    private function getJadwalOperasiPasienVal($input){
        $rule = [
            'nopeserta' => 'required|string'
        ];

        $message = [
            'required' => 'Isian : attribut harus terisi'
        ];

        $validate = Validator::make($input,$rule,$message);
        if ($validate->fails()) {
            $error = $validate->errors()->first();
            return $error;
        }else{
            return "Ok";
        }
    }

    private function getAntreanVal($input){
        $rule = [
            'nomorkartu' => 'required|string',
            'nohp' => 'required|string',
            'kodepoli' => 'required|string',
            'tanggalperiksa' => 'required|string',
            'kodedokter' => 'required|numeric',
            'jampraktek' => 'required|string',
            'jeniskunjungan' => 'required|numeric',
            'nomorreferensi' => 'required|string',
        ];

        $message = [
            'required' => 'Isian : attribut harus terisi',
            'numeric' => 'Isian : attribut harus berupa angka',
            'string' => 'Isian : attribut harus berupa huruf',
        ];

        $validate = Validator::make($input,$rule,$message);

        if ($validate->fails()) {
            $error = $validate->errors();
            return $error;
        }else{
            return "Ok";
        }
    }

    /** ============================= Data Section ============================================ */

    public function getJadwalOperasi(Request $request){
        try {
            $error = $this->getJadwalOperasiVal($request->input());
            if ($error !== "Ok") throw new \Exception($error, 201);

            $jadwalOperasi = new JadwalOperasi();

            // Data Request
            $tanggalawal  = $request->input('tanggalawal');
            $tanggalakhir = $request->input('tanggalakhir');

            // Inject Testing
            // $tanggalawal  = '2022-01-01';
            // $tanggalakhir = '2022-01-31';

            $data = $jadwalOperasi->jadwalOperasi($tanggalawal, $tanggalakhir);

            $data = $data->map(function($value){
                $date = Carbon::now();
                $result = (object)[];
                $result->kodebooking     = $value->kodebooking;
                $result->tanggaloperasi  = $value->tanggaloperasi;
                $result->jenistindakan   = $value->jenistindakan;
                /** Revisi Egova */
                // $result->kodepoli        = $value->kodepoli;
                // $result->namapoli        = $value->namapoli;
                $result->terlaksana      = (int)$value->terlaksana;
                $result->nopeserta       = $value->nopeserta ? : '';
                $result->lastupdate      = (int) ($date->timestamp . "000");
                return $result;
            });

        } catch (\Exception $e) {
            $code = $e->getCode() ? $e->getCode() : 201;
            $message = $e->getMessage();
            return response()->json([
                'response' => [
                    'list' => []
                ],
                'metadata' => [
                    'message' => $message,
                    'code' => $code
                ],

            ], $code);
        }

        return response()->json([
            'response' => [
                'list' => $data
            ],
            'metadata' => [
                'message' => 'Ok',
                'code'    => 200
            ],
            ]);
    }

    public function getJadwalOperasiPasien(Request $request){
        try {
            $error = $this->getJadwalOperasiPasienVal($request->input());
            if($error !== "Ok" ) throw new \Exception($error, 201);

            $jadwalOperasi = new JadwalOperasi();
            $nopeserta = $request->input('nopeserta');

            // Data inject
            // $nopeserta = '0000016015599';

            $data = $jadwalOperasi->jadwalOperasiPasien($nopeserta);
            $data->map(function($value){
                $result = (object)[];
                $result->kodebooking = $value->kodebooking;
                $result->tanggaloperasi = $value->tanggaloperasi;
                $result->jenistindakan = $value->jenistindakan;
                /** Revisi Egova */
                // $result->kodepoli = $value->kodepoli;
                // $result->namapoli = $value->namapoli;
                $result->terlaksana = (int)$value->terlaksana;

                return $result;
            });

        } catch (\Exception $e) {
            $code = $e->getCode() ? $e->getCode() : 201;
            $message = $e->getMessage();
            return response()->json([
                'response' => [
                    'list' => []
                ],
                'metadata' => [
                    'message' => $message,
                    'code' => $code
                ],
            ]);
        }

        return response()->json([
            'response' => [
                'list' => $data
            ],
            'metadata' => [
                'message' => 'Ok',
                'code' => 200
            ]
        ]);
    }

    public function getAntrean(Request $request){
        try {
            // Validasi data
            $error = $this->getAntreanVal($request->input());
            if($error !== "Ok" ) throw new \Exception($error, 201);

            // Validasi tanggal
            $tanggalPeriksa = Carbon::parse($request->get('tanggalperiksa'));
            $tanggalSekarang = Carbon::parse(date('Y-m-d'));
            $tanggalBatas = date('Y-m-d', strtotime('+7 Days'));

            // Jika tanggal sudah lewat
            if($tanggalPeriksa < $tanggalSekarang) throw new \Exception("Tanggal pemeriksaan sudah melewati tanggal sekarang (" . $tanggalSekarang->format('d-m-Y') . ")", 201);

            // Jika tanggal lebih dari 7 hari
            if($tanggalPeriksa >= $tanggalBatas) throw new \Exception("Maaf, Silahkan mendaftar H-7 sebelum tanggal pemeriksaan", 201);

            // Jika ambil antrian pada hari H
            if($tanggalPeriksa == $tanggalSekarang) throw new \Exception("Maaf, Booking Online ditutup H-1 sebelum tanggal pemeriksaan", 201);

            $data = (new Antrian)->ambilAntrean($request);
            // Cek bahwa data tidak ada yang error
            if($data->code != 200) throw new \Exception($data->message, $data->code);

            $result                     = $data->data;
            $response                   = (object)[];

            $response->nomorantrean     = "$result->nomor";
            $response->angkaantrean     = (int)$result->nomor;  // Baru
            $response->kodebooking      = $result->antrian_id;
            $response->no_mr            = $result->nomr;        // Baru
            $response->namapoli         = $result->layanan;
            $response->namadokter       = $result->dokter;
            $response->estimasidilayani = $result->estimasi;

            $response->sisakuotajkn     = (int)$result->kuota - (int)$result->nomor;                                // Cari Sisa Kuota JKN
            $response->kuotajkn         = (int)$result->kuota;
            $response->sisakuotanonjkn  = (int)$result->kuota - (int)$result->nomor;
            $response->kuotanonjkn      = (int)$result->kuota;

            $response->keterangan       = 'Peserta harap 60 menit lebih awal guna pencatatan administrasi.';

            // Rekam data post dari BPJS
            // PostAntrean::create([
            //     'simrs_antrian_kode'  => $result->antrian_id,
            //     'post_nomorkartu'     => $request->get('nomorkartu'),
            //     'post_nik'            => $request->get('nik'),
            //     'post_nohp'           => $request->get('nohp'),
            //     'post_kodepoli'       => $request->get('kodepoli'),
            //     'post_norm'           => $request->get('norm'),
            //     'post_tanggalperiksa' => $request->get('tanggalperiksa'),
            //     'post_kodedokter'     => $request->get('kodedokter'),
            //     'post_jampraktek'     => $request->get('jampraktek'),
            //     'post_jeniskunjungan' => $request->get('jeniskunjungan'),
            //     'post_nomorreferensi' => $request->get('nomorreferensi'),
            // ]);

        } catch (\Exception $e) {
            $code = $e->getCode() ? $e->getCode() : 201;
            $message = $e->getMessage();
            return response()->json([
                'response' => [
                    'list' => []
                ],
                'metadata' => [
                    'message' => $message,
                    'code' => $code
                ],
            ]);
        }

        return response()->json([
            'response' => [
                'list' => $response
            ],
            'metadata' => [
                'message' => 'Ok',
                'code' => 200
            ]
        ]);

    }

    public function getBatal(Request $request){
        try {
            // $error = $this->batalVal($request->input());
            // if($error !== "Ok" ) throw new \Exception($error, 201);

            $input = collect($request);
            //parameter
            $kodeBooking = $input->get('kodebooking');
            $data = (new Antrian)->batalBooking($kodeBooking);
            if($data->code != 200) throw new \Exception($data->message, $data->code);

        } catch (\Exception $e) {
            $code = $e->getCode() ? $e->getCode() : 201;
            $message = $e->getMessage();
            return response()->json([
                'response' => [
                    'list' => []
                ],
                'metadata' => [
                    'message' => $message,
                    'code' => $code
                ],
            ]);
        }
        return response()->json([
            'metadata' => [
                'message' => 'Ok',
                'code' => 200
            ]
        ]);

    }

    public function getStatus(Request $request){
        try {
            $input = collect($request);

            $res = (new Antrian)->statusAntrean($input->only('kodepoli','kodedokter','tanggalperiksa'));

            if($res->code != 200) throw new \Exception('Maaf, Terjadi kesalahan dalam proses data', 201);

        } catch (\Exception $e) {
            return response()->json([
                'metadata' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode()
                    ]
                ]);
        }
        return response()->json([
            'response' => $res->response,
            'metadata' => [
                'message' => 'Ok',
                'code' => 200
                ]
            ]);
    }

    public function getSisa(Request $request){
        try {
            $res = (new Antrian)->sisaAntrean($request->kodebooking);

            if($res->code != 200) throw new \Exception('Maaf, Terjadi kesalahan dalam proses data', 201);

        } catch (\Exception $e) {
            return response()->json([
                'metadata' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode()
                    ]
                ]);
        }
        return response()->json([
            'response' => $res->response,
            'metadata' => [
                'message' => 'Ok',
                'code' => 200
                ]
            ]);
    }

    public function getCheckin(Request $request){
        // try {
        //     PostChekin::create([
        //         'post_kodebooking' => $request->kodebooking,
        //         'post_waktu' => $request->waktu,
        //     ]);
        // } catch (QueryException $e) {
        //     return response()->json([
        //         'metadata' => [
        //             'message' => $e->getMessage(),
        //             'code' => $e->getCode()
        //             ]
        //         ]);
        // }
        // return response()->json([
        //     'metadata' => [
        //         'message' => 'Ok',
        //         'code' => 200
        //         ]
        //     ]);
    }

    public function getAllAntrean(){
        $data = (new Antrian)->tarikAntrean();
        return $data;
    }

    public function postUpdateWaktuAntrean(Request $request){
        $endpoint = 'antrean/add';
        $data = json_encode($request->all());
        $res = $this->bridging->postRequestAntrol($endpoint, $data);
        return $res;
    }
}
