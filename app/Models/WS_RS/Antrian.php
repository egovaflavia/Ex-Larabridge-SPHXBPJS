<?php

namespace App\Models\WS_RS;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB as DB;

class Antrian extends Model
{
    protected $antrian;

    public function __construct(){
        $this->antrian = new Antrian();
    }

    public function ambilAntrean($input){
        try {
            dd($input);
            $input          = collect($input);
            $noKartu        = $input->get('nomorkartu');
            $nik            = $input->get('nik');
            $no_telp        = $input->get('nohp');
            $layanan        = $input->get('kodepoli');
            $tanggal        = $input->get('tanggalperiksa');
            $nomor_ref      = $input->get('nomorreferensi');
            $nomor_mr       = $input->get('norm');
            $kodeDokterBpjs = $input->get('kodedokter');
            $jamPraktek     = $input->get('jampraktek');
            $jenisKunjungan = $input->get('jeniskunjungan');

            if($jenisKunjungan == 1){
                $jenisKunjungan = "Rujukan FKTP";
            }elseif($jenisKunjungan == 2){
                $jenisKunjungan = "Rujukan Internal";
            }elseif($jenisKunjungan == 3){
                $jenisKunjungan = "Kontrol";
            }elseif($jenisKunjungan == 4){
                $jenisKunjungan = "Rujukan Antar RS";
            }
            // Cek layanan
            // $layanan = $this->layanan($layanan);
            // if(!$layanan) throw new \Exception("Maaf, Layanan tidak tersedia", 201);

            // Validasi tanggal
            $tgl_sekarang = date('Y-m-d');
            $tgl_batas = date('Y-m-d', strtotime('+7 Days'));

            if($tanggal >= $tgl_batas) throw new \Exception("Maaf, Silahkan mendaftar H-7 sebelum tanggal pemeriksaan", 403);

            if($tanggal == $tgl_sekarang) throw new \Exception("Maaf, Booking Online ditutup H-1 sebelum tanggal pemeriksaan");

            if($tanggal < $tgl_sekarang) throw new \Exception("Tanggal pemeriksaan sudah melewati tanggal sekarang (" . $tanggal . ")", 403);

            //referensi jam batas berdasarkan tanggal
            $ref_time = $tanggal == $tgl_sekarang ? date("H:i") : "00:00";
            // $ref_time = $jamPraktek = explode('-', $jamPraktek  )[1];

            // Cek dokter praktek
            $listDokter = $this->dokterPraktek($layanan, $tanggal, $kodeDokterBpjs, $jamPraktek);
            // dd($ref_time);

            // ambil jadwal dokter yang belum belum selesai
            $listDokter = $listDokter->filter(function($q) use($ref_time) {
                return ($ref_time) < $q->keluar;
            });

            // Cek jam berakhir praktek dokter
            $a = collect($listDokter);
            if (empty($a->toArray())) {
                throw new \Exception("Jadwal dokter tidak ditemukan", 201);
            }

            $data_pasien = (object)[];
            $data_pasien->no_mr = $nomor_mr;

            // ambil data pasien dari simrs
            $data = $this->cariPasien($data_pasien, $noKartu, $nik);

            if($data->code!==200) throw new \Exception($data->message, $data->code);

            $data_pasien      = $data->response;
            $pasien_mr        = $data_pasien->fs_mr;
            $pasien_nama      = $data_pasien->fs_nm_pasien;
            $pasien_tgl_lahir = $data_pasien->fd_tgl_lahir;
            $pasien_alamat    = $data_pasien->fs_alm_pasien;
            $pasien_telp      = $data_pasien->fs_tlp_pasien;
            // $pasien_ket = $data_pasien->fs_keterangan;
            $pasien_ket     = ' Jenis Kunjungan : '. $jenisKunjungan;
            $pasien_rujukan = $nomor_ref;
            $pasien_kartu   = $noKartu;
            $pasien_nik     = $nik;

            /* ambil data dokter referensi */

            $dokter = null;
            if($listDokter->count()){
                $dokter = $listDokter->sortBy(function($q){
                    return [$q->daftar, $q->keluar];
                })->first();
            } else {
                throw new \Exception("Maaf, Jadwal Dokter tidak ditemukan", 401);
            }

            /**
             * WAKTU SIMRS : 1 - Minggu, 2 - Senin , ... , 7 - Sabtu
             * WAKTU PHP : 7 - Minggu, 1 - Senin, ... , 6 - Sabtu
             */
            $simrs_hari = (date('N', strtotime($tanggal)) + 1) % 7;
            $simrs_hari = $simrs_hari == 0 ? 7 : $simrs_hari;

            $data_layanan = $this->layanan($layanan);

            //ambil jadwal di simrs berdasarkan kode dokter
            $simrs_jadwal = DB::connection('simrs')
                    ->table('TA_JADWAL_DOKTER AS aa')
                    ->where('aa.fs_kd_dokter', $listDokter->pluck('dokter_id'))
                    ->where('aa.fs_kd_layanan', $data_layanan->fs_kd_layanan)
                    ->where('aa.fn_hari', $simrs_hari)
                    ->selectRaw("aa.fs_kd_dokter, aa.fs_jam_mulai, aa.fs_jam_selesai, aa.fs_kd_sesion_poli, aa.fn_max")
                    ->get();

            // Kalkulasi jadwal dan jadwal praktek
            $jadwal = $listDokter->map(function($q) use($simrs_jadwal, $dokter, $ref_time){
                // Cek kode dokter tb_jadwal dan simrs apakah sama
                $cekA = $simrs_jadwal->where('fs_kd_dokter', $q->dokter_id)->first();
                if(!$cekA) throw new \Exception('Maaf, terjadi kesalahan internal, Silahkan hubungi rumah sakit', 201);

                $res = (object)[];
                $res->dokter_id      = $q->dokter_id;
                $res->nama           = $q->dokter;
                $res->jam_masuk      = $q->masuk;
                $res->jam_keluar     = $q->keluar;
                $res->daftar         = $q->daftar;
                $res->jam_pelayanan  = $cekA->fs_jam_mulai;
                $res->sesi_pelayanan = $cekA->fs_kd_sesion_poli;
                $res->alternatif     = $dokter == $q->dokter_id ? true : false;
                $res->kuota          = $cekA->fn_max;

                $res->time = $ref_time;
                return $res;
            })->sortBy(function($q){
                return [$q->daftar, $q->jam_keluar];
            });

             //referensi jadwal
            $jadwal_ref = $jadwal->sortByDesc('alternatif')->first();

            $object = (object)[
                'layanan'          => $data_layanan->fs_kd_layanan,
                'nama_layanan'     => $data_layanan->fs_nm_layanan,
                'tgl_pelayanan'    => $tanggal,
                'jam_pelayanan'    => $jadwal_ref->jam_pelayanan,
                'sesi_pelayanan'   => $jadwal_ref->sesi_pelayanan,
                'dokter'           => $jadwal_ref->dokter_id,
                'nama_dokter'      => $jadwal_ref->nama,
                'jam_masuk'        => $jadwal_ref->jam_masuk,
                'pasien_mr'        => $pasien_mr,
                'pasien_nama'      => $pasien_nama,
                'pasien_tgl_lahir' => $pasien_tgl_lahir,
                'pasien_alamat'    => $pasien_alamat,
                'pasien_telp'      => $pasien_telp,
                'pasien_ket'       => $pasien_ket,
                'sys_time'         => $ref_time,
                'pasien_rujukan'   => $pasien_rujukan,
                'pasien_kartu'     => $pasien_kartu,
                'pasien_nik'       => $pasien_nik,
                'dokter_kuota'     => $jadwal_ref->kuota
            ];

            // Cek Booking
            $data = $this->checkBooking($object);

            // Jika ada data bookingnya
            if($data->code !== 200) throw new \Exception($data->message, $data->code);

            // It's magic bellow
            $do_simrs = DB::connection('simrs')->transaction(function() use($object){
                $tgl_pelayanan = $object->tgl_pelayanan;
                $layanan       = $object->layanan;

                $dokter         = $object->dokter;
                $dokter_kuota   = $object->dokter_kuota;
                $sesi_pelayanan = $object->sesi_pelayanan;
                $jam_pelayanan  = $object->jam_pelayanan;

                $sys_date = date('Y-m-d');
                $sys_time = date('H:i:s');
                $sys_user = 'BOOKING';

                $nama_dokter  = $object->nama_dokter;
                $nama_layanan = $object->nama_layanan;
                $jam_masuk    = $object->jam_masuk;

                $pasien_nama      = $object->pasien_nama;
                $pasien_mr        = $object->pasien_mr;
                $pasien_alamat    = $object->pasien_alamat;
                $pasien_telp      = $object->pasien_telp;
                $pasien_tgl_lahir = $object->pasien_tgl_lahir;
                $pasien_rujukan   = $object->pasien_rujukan;
                $pasien_kartu     = $object->pasien_kartu;
                $pasien_nik       = $object->pasien_nik;
                $pasien_ket       = $object->pasien_ket;
                $keterangan       = "NIK:" . $pasien_nik . $pasien_ket;

                if(strlen($pasien_alamat) > 38){
                    $keterangan .= ", ALT: " . substr($pasien_alamat,38);
                    $pasien_alamat = substr($pasien_alamat, 0, 38);
                }
                $keterangan = substr($keterangan, 0, 120);

                //check nomor antrian
                $next_number = DB::connection('simrs')
                        ->table('ta_trs_antrian')
                		->where('fd_tgl_antrian',$tgl_pelayanan)
                		->where('fs_kd_layanan',$layanan)
                		->where('fs_kd_petugas_medis',$dokter)
                		->where('fs_kd_sesion_poli',$sesi_pelayanan)
                		->where('fs_jam_mulai',$jam_pelayanan)
                		->selectRaw('MAX(fn_no_urut) AS numb')
                        ->first()->numb;
                $book_number = $next_number ? (int)$next_number + 1 : 1;

                //ambil kode booking untuk table TA_TRS_BOOKING
                $book_id = DB::connection('simrs')
                        ->table('tz_parameter_no')
                        ->where('fs_kd_parameter','NOBOOKING')
                        ->selectRaw('fn_value AS numb')
                        ->first()->numb;

                $book_id = "BO" . substr("0000000",0,(7-strlen($book_id))) . $book_id . "A";

                $trs = DB::connection('simrs')
                        ->update("UPDATE tz_parameter_no SET fn_value = fn_value + 1 WHERE  fs_kd_parameter = 'NOBOOKING' ");
                if(!$trs) throw new \Exception("(TRX2) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //simpan data ke table TA_TRS_ANTRIAN
                $trs = DB::connection('simrs')
                        ->table('ta_trs_booking')
                        ->insert([
                            'fs_kd_trs'             => $book_id,
                            'fd_tgl_trs'            => $sys_date,
                            'fs_jam_trs'            => $sys_time,
                            'fs_kd_petugas'         => $sys_user,
                            'fs_mr'                 => $pasien_mr,
                            'fs_nm_pasien_book'     => $pasien_nama,
                            'fs_alm_pasien_book'    => $pasien_alamat,
                            'fs_tlp_pasien_book'    => $pasien_telp,
                            'fd_tgl_lahir_book'     => $pasien_tgl_lahir,
                            'fs_kd_layanan'         => $layanan,
                            'fs_kd_layanan2'        => ' ',
                            'fs_kd_layanan3'        => ' ',
                            'fs_keterangan'         => $keterangan,
                            'fs_kd_petugas_medis'   => $dokter,
                            'fs_kd_petugas_medis2'  => ' ',
                            'fs_kd_petugas_medis3'  => ' ',
                            'fd_tgl_periksa'        => $tgl_pelayanan,
                            'fs_jam_periksa'        => '00:00:00',
                            'fs_kd_sesion_poli'     => $sesi_pelayanan,
                            'fb_prioritas'          => 0,
                            'fs_jam_mulai'          => $jam_pelayanan,
                            'fs_kd_sesion_poli2'    => ' ',
                            'fs_jam_mulai2'         => '00:00',
                            'fs_kd_sesion_poli3'    => ' ',
                            'fs_jam_mulai3'         => '00:00',
                            'fs_kd_cara_booking_dk' => 'JK',
                            'fs_referred'           => ' ',
                            'fs_referred_no'        => ' ',
                            'fs_no_rujukan_bpjs'    => $pasien_rujukan,
                            'fs_no_peserta_bpjs'    => $pasien_kartu
                        ]);

                if(!$trs) throw new \Exception("(TRX2) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //ambil kode antrian untuk table TA_TRS_ANTRIAN
                $antr_id = DB::connection('simrs')
                                ->table('tz_parameter_no')
                                ->where('fs_kd_parameter','NOANTRIAN')
                                ->selectRaw('fn_value AS numb')
                                ->first()->numb;
                $antr_id = "AN" . substr('0000000', 0,(7-strlen($antr_id))) . $antr_id . "A";

                $trs =  DB::connection('simrs')
                        ->update("UPDATE tz_parameter_no SET fn_value = fn_value + 1 WHERE  fs_kd_parameter = 'NOANTRIAN'");

                if(!$trs) throw new \Exception("(TRX3) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //simpan data ke table TA_TRS_ANTRIAN
                $trs = DB::connection('simrs')
                        ->table('ta_trs_antrian')
                        ->insert([
                            'fs_kd_trs'            => $antr_id,
                            'fd_tgl_trs'           => $sys_date,
                            'fs_jam_trs'           => $sys_time,
                            'fs_kd_petugas'        => $sys_user,
                            'fs_mr'                => $pasien_mr,
                            'fs_nm_pasien_antrian' => $pasien_nama,
                            'fs_kd_layanan'        => $layanan,
                            'fs_kd_petugas_medis'  => $dokter,
                            'fd_tgl_antrian'       => $tgl_pelayanan,
                            'fb_prioritas'         => 0,
                            'fs_kd_sesion_poli'    => $sesi_pelayanan,
                            'fs_jam_mulai'         => $jam_pelayanan,
                            'fn_no_urut'           => $book_number,
                            'fs_kd_fruang'         => ' ',
                            'fs_prefix_antrian'    => ' ',
                        ]);

                if(!$trs) throw new \Exception("(TRX4) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //check apakah sudah ada data di table TA_NO_ANTRIAN_MAP
                $check = DB::connection('simrs')
                		->table('ta_no_antrian_map')
                		->where('fs_kd_dokter',$dokter)
                		->where('fs_kd_layanan',$layanan)
                		->where('fd_tgl_jadwal',$tgl_pelayanan)
                		->where('fs_jam_jadwal',$jam_pelayanan)
                        ->count();

                //jika baris kurang dari nomor antrian terbaru
                if($check < $book_number){
                    $ct = 5;
                    for($it = 1; $it <= $ct; $it++){
                        $trs = DB::connection('simrs')
                                ->table('ta_no_antrian_map')
                                ->insert([
                						'fs_kd_layanan' => $layanan,
                						'fs_kd_dokter'  => $dokter,
                						'fd_tgl_jadwal' => $tgl_pelayanan,
                						'fs_jam_jadwal' => $jam_pelayanan,
                						'fn_no_antrian' => $check + $it,
                						'fs_flag'       => 'AUTO',
                						'fs_mr'         => ' ',
                						'fs_nm_pasien'  => ' ',
                						'fs_kd_trs_gen' => ' ',
                						'CRTTGL'        => $sys_date,
                						'CRTJAM'        => $sys_time,
                						'CRTIPA'        => '192.168.159.4\BOOKING',
                						'CRTVER'        => '11.6.6021',
                						'CRTUSR'        => $sys_user,
                                    ]);
                        if(!$trs) throw new \Exception("(TRX5) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);
                    }
                }

                //simpan data ke TA_NO_ANTRIAN_MAP
                $trs = DB::connection('simrs')
                        ->table('ta_no_antrian_map')
                        ->where('fs_kd_dokter',$dokter)
                        ->where('fs_kd_layanan',$layanan)
                        ->where('fd_tgl_jadwal',$tgl_pelayanan)
                        ->where('fs_jam_jadwal',$jam_pelayanan)
                        ->where('fn_no_antrian', $book_number)
                        ->update([
                            'fs_nm_pasien'   => $pasien_nama,
                            'fs_mr'          => $pasien_mr,
                            'fs_kd_trs_gen'  => $book_id,
                            'fs_ket_trs_gen' => 'Booking'
                        ]);
                if(!$trs) throw new \Exception("(TRX6) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //UPDATE table TA_TRS_BOOKING untuk data kode TA_TRS_ANTRIAN
                $trs = DB::connection('simrs')
                        ->table('ta_trs_booking')
                        ->where('fs_kd_trs',$book_id)
                        ->update(['fs_kd_trs_antrian' => $antr_id]);

                if(!$trs) throw new \Exception("(TRX7) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //UPDATE table TA_TRS_ANTRIAN untuk kosongkan data registrasi
                $trs = DB::connection('simrs')
                        ->table('ta_trs_antrian')
                        ->where('fs_kd_trs',$antr_id)
                        ->update(['fs_kd_reg' => ' ',]);

                if(!$trs) throw new \Exception("(TRX8) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                //kalkulasi data estimasi dilayani berdasarkan jam mulai praktek
                $estimasi_time = (int)$book_number * 10;
                $estimasi = Carbon::parse($tgl_pelayanan . ' ' . $jam_masuk)->addMinutes($estimasi_time);
                $estimasi = (int) ($estimasi->timestamp . "000");

                return (object)[
                    'book_id'    => $book_id,
                    'antrian_id' => $antr_id,
                    'nomor'      => $book_number,
                    'layanan'    => $nama_layanan,
                    'dokter'     => $nama_dokter,
                    'estimasi'   => $estimasi,
                    'nomr'       => $pasien_mr,
                    'kuota'      => $dokter_kuota,
                ];
            });

        } catch (\Illuminate\Database\QueryException $e) {
            return (object)[
                'code' => 201,
                'message' => '(DB:01GA) Maaf terjadi kesalahan dalam proses data'
            ];
        }catch (\Exception $e){
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'message' => 'Ok',
            'data' => $do_simrs
        ];
    }

    public function checkBooking($object){
        try {
            $tgl_pelayanan    = $object->tgl_pelayanan;
            $layanan          = $object->layanan;
            $pasien_mr        = $object->pasien_mr;
            $pasien_nama      = $object->pasien_nama;
            $pasien_tgl_lahir = $object->pasien_tgl_lahir;
            $pasien_telp      = $object->pasien_telp;

            //chek data booking
            $check = DB::connection('simrs')
                    ->table('ta_trs_booking')
                    ->where('fd_tgl_void', '3000-01-01')
                    ->where('fd_tgl_periksa', $tgl_pelayanan)
                    ->where('fs_kd_layanan', $layanan)
                    ->where('fs_nm_pasien_book', $pasien_nama)
                    ->where('fd_tgl_lahir_book', $pasien_tgl_lahir)
                    ->where('fs_tlp_pasien_book', $pasien_telp)
                    ->where('fs_mr', $pasien_mr ? : '')
                    ->selectRaw('fs_mr,fd_tgl_trs + fs_jam_trs as tgl_trs, fs_kd_trs, fs_kd_trs_antrian')
                    ->orderBy('tgl_trs','desc')
                    ->first();

            if($check){
                if($check->fs_mr == $pasien_mr){
                    if($tgl_pelayanan == date('Y-m-d')){
                        throw new \Exception("Pasien sudah booking untuk hari ini, silahkan hubungi pihak RS",201);
                    }
                    $kodeAntrian = DB::connection('simrs')
                        ->table('TA_TRS_ANTRIAN')
                        ->where('FS_KD_TRS', $check->fs_kd_trs_antrian)
                        ->first();
                    throw new \Exception("Pasien sudah melakukan booking untuk tanggal " . $tgl_pelayanan .' Dengan kode Booking '.$kodeAntrian->FS_KD_TRS , 201);
                }
            }

            //check data registrasi
            // if($pasien_mr){
            //     $check = DB::connection('simrs')
            //         ->table('TA_TRS_ANTRIAN AS aa')
            //         ->leftjoin('TA_REGISTRASI AS bb', 'aa.fs_kd_trs','bb.fs_kd_trs_antrian')
            //         ->where('aa.fd_tgl_void', '3000-01-01')
            //         ->where('aa.fd_tgl_antrian', $tgl_pelayanan)
            //         ->where('aa.fs_kd_layanan', $layanan)
            //         ->where('aa.fs_mr', $pasien_mr)
            //         ->selectRaw("bb.fs_kd_reg")
            //         ->first();
            //     if($check){
            //         throw new \Exception("Pasien sudah registrasi ke RS (*" . $check->fs_kd_reg . ")", 201);
            //     }
            // }
        } catch (\Exception $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'message' => 'OK'
        ];
    }

    public function layanan($kode){
        if(!$kode) return null;
        $layanan = DB::connection('simrs')
                ->table('TA_LAYANAN AS aa')
                ->leftjoin('TA_LAYANAN_BPJS AS bb', 'aa.fs_kd_layanan_bpjs', 'bb.fs_kd_layanan_bpjs')
                ->where('aa.fs_kd_layanan_bpjs', $kode)
                ->selectRaw("aa.fs_kd_layanan, aa.fs_nm_layanan, aa.fs_kd_layanan_bpjs, bb.fs_nm_layanan_bpjs")
                ->first();
        return $layanan;
    }

    public function dokterPraktek($layanan, $tanggalPeriksa, $kodeDokterBpjs, $jamPraktek){
        $jamPraktek = explode('-', $jamPraktek  )[0];
        $tanggalPeriksa = Carbon::parse($tanggalPeriksa);
        $hari = $tanggalPeriksa->format('N');

        if ($hari == 7) {
            return collect();
        }

        // Ambil kode dokter berdasarkan layanan dan kode dokter
        $dokter =   DB::connection('mysph')
                    ->table('ta_dokter AS aa')
                    ->where('dokter_kode_bpjs', $kodeDokterBpjs)
                    ->where('bpjs_subs_kode', $layanan)
                    ->where('dokter_praktek', 'Y')
                    ->select('*')
                    ->get();
        dd($dokter);

        // Ambil id dokter
        $dokter_id = $dokter->pluck('dokter_id_jadwal');

        // Cari jadwal dokter yang belum expired
        $jadwal = DB::connection('mysph')
            ->table('tb_jadwal AS aa')
            ->where(function($query){
                $tgl = date('Y-m-d');
                $query->whereRaw("'" . $tgl . "' BETWEEN aa.jadwal_tanggal_berlaku AND aa.jadwal_tanggal_expired")
                ->orWhereRaw("'" . $tgl . "' < aa.jadwal_tanggal_berlaku");
            })
            ->where('aa.hari_id', $hari)
            ->whereIn('aa.dokter_id', $dokter_id)
            ->get();

        // Cek konfirmasi praktek
        $praktek = DB::connection('mysph')
            ->table('tb_jadwal_log AS aa')
            ->whereIn('aa.jadwal_id', $jadwal->pluck('jadwal_id'))
            ->where('aa.log_tanggal', $tanggalPeriksa)
            ->get();

        // dd($praktek);

        $data_layanan = $this->layanan($layanan);

        $pasien = DB::connection('simrs')
                ->table("TA_TRS_ANTRIAN AS aa")
                ->where('aa.fd_tgl_void', '3000-01-01')
                ->where('aa.fd_tgl_antrian', $tanggalPeriksa->format('Y-m-d'))
                ->where('aa.fs_kd_layanan', $data_layanan->fs_kd_layanan)
                ->selectRaw("aa.fs_kd_petugas_medis AS fs_kd_dokter, COUNT(aa.fn_no_urut) AS terdaftar")
                ->groupBy('fs_kd_petugas_medis')
                ->get();

        // dd($pasien);

        $jadwal_tersedia = $jadwal->map(function($q) use($dokter, $praktek, $pasien){

            $j_kuota  = $q->jadwal_kuota ? : 0;
            $j_daftar = 0;
            $j_masuk  = $q->jadwal_jam_masuk;
            $j_keluar = $q->jadwal_jam_keluar;
            $j_kuota  = 5;

            $j_praktek = $praktek->where('jadwal_id', $q->jadwal_id)->first();

            if($j_praktek){
                if($j_praktek->log_status == 'T') return null;

                $j_masuk = $j_praktek->log_konfirmasi_masuk ? : $j_masuk;
                $j_keluar = $j_praktek->log_konfirmasi_keluar ? : $j_keluar;
                $j_kuota = $j_praktek->log_kuota ? : $j_kuota;
            }

            $j_dokter = $dokter->where('dokter_id_jadwal', $q->dokter_id)->first();

            $res = (object)[];
            $res->id = $q->jadwal_id;
            $res->dokter_id = $j_dokter->dokter_kode_medis;
            $res->dokter = $j_dokter->dokter_nama_gelar;
            $res->masuk = $j_masuk;
            $res->keluar = $j_keluar;
            // $res->kuota = $j_kuota;
            $res->daftar = $j_daftar;

            return $res;
        })->sortBy('masuk')->filter()->flatten();
        // dd($jadwal_tersedia);
        return $jadwal_tersedia;
    }

    public function cariPasien($object, $noPolis = null, $nik = null){
        try {
            if($noPolis){

                /* Cek No Polis */

                $data = DB::connection('simrs')
                ->table('TA_POLIS as AA')
                ->leftjoin('TA_POLIS_COVER as BB', 'AA.FS_KD_POLIS', '=', 'BB.FS_KD_POLIS')
                ->leftjoin('TC_MR as CC', 'BB.FS_MR', '=', 'CC.FS_MR')
                ->where('AA.FS_NO_POLIS', $noPolis)
                ->select(
                    'CC.FS_MR AS fs_mr',
                    'FS_NO_POLIS AS fs_kd_polis',
                    'FS_ATAS_NAMA AS fs_nm_pasien',
                    'FS_KD_IDENTITAS AS nik',
                    'FS_ALM_PASIEN AS fs_alm_pasien',
                    'FD_TGL_LAHIR AS fd_tgl_lahir',
                    'FS_TLP_PASIEN AS fs_tlp_pasien',
                    'FS_JNS_KELAMIN AS fs_jns_kelamin'
                    )
                ->first();
            }
            if (!$data) {
                throw new \Exception('Maaf data rekam medis tidak ditemukan', 201);
            }

        } catch(\Illuminate\Database\QueryException $e){
            return (object)[
                'code' => 403,
                'message' => '(DB:01) Maaf, Terjadi kesalahan dalam proses data'
            ];
        } catch (\Exception $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'message' => 'OK',
            'response' => $data,
        ];
    }

    public function batalBooking($input){
        try {
            DB::connection('simrs')->transaction(function() use($input){
                $kodeBooking = $input;
                $sys_date = date('Y-m-d');
                $sys_time = date('H:i:s');

                 // Ambil data yang di inginkan berdasarkan kodebooking
                $data = DB::connection('simrs')
                ->table('ta_trs_booking AS aa')
                ->leftjoin('ta_trs_antrian AS bb', 'aa.fs_kd_trs_antrian', 'bb.fs_kd_trs')
                ->select(
                    'aa.fs_kd_trs as booking',
                    'aa.fs_kd_trs_antrian as antrean',
                    'aa.fs_kd_layanan as kdpoli',
                    'aa.fd_tgl_periksa as tgl_periksa',
                    'bb.fs_jam_mulai as jam_periksa',
                    'aa.fs_kd_petugas_medis as dokter',
                    'bb.fn_no_urut as no_urut',
                    )
                ->where('aa.fs_kd_trs_antrian', $kodeBooking)
                ->first();
                if(!$data) throw new \Exception("(DEL:1) Kode booking tidak ditemukan, Silahkan Ulangi", 201);

                // Step 1
                $trx1 = DB::connection('simrs')
                        ->table('ta_trs_booking')
                        ->where('fs_kd_trs' , $data->booking)
                        // ->select('*')->get();
                        ->update(['fd_tgl_void' => $sys_date,'fs_jam_void' => $sys_time,'fs_kd_petugas_void' => 'BOOKING',]);
                if(!$trx1) throw new \Exception("(DEL:2) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                // Step 2
                $trx2 = DB::connection('simrs')
                        ->table('ta_trs_antrian')
                        ->where('fs_kd_trs',$data->antrean)
                        ->update([
                            'fd_tgl_void' => $sys_date,
                            'fs_jam_void' => $sys_time,
                            'fs_kd_petugas_void' => 'BOOKING'
                        ]);
                if(!$trx2) throw new \Exception("(DEL:3) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

                $trx3 = DB::connection('simrs')
                ->table('ta_no_antrian_map')
                ->where('fs_kd_dokter', $data->dokter)
                ->where('fs_kd_layanan', $data->kdpoli)
                ->where('fd_tgl_jadwal', $data->tgl_periksa)
                ->where('fs_jam_jadwal', $data->jam_periksa)
                ->where('fn_no_antrian', $data->no_urut)
                ->update([
                    'fs_nm_pasien'  => ' ',
                    'fs_mr' => ' ',
                    'fs_kd_trs_gen' => ' ',
                    'fs_ket_trs_gen' => ' ',
                ]);
                if(!$trx3) throw new \Exception("(DEL:4) Terjadi Kesalahan Proses, Silahkan Ulangi", 201);

            });

        } catch (\Exception $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'message' => 'Ok'
        ];
    }

    public function statusAntrean($input){
        try {
            $layanan = $this->layanan($input['kodepoli']);
            // dd($layanan);
            $layananKode = $layanan->fs_kd_layanan;
            $layananNama = $layanan->fs_nm_layanan;
            $tanggal = $input['tanggalperiksa'];
            $kodeDokter = $input['kodedokter'];

            $dokter = DB::connection('mysph')
            ->table('ta_dokter AS aa')
            ->where('dokter_kode_bpjs', $kodeDokter)
            ->select("*")
            ->first();

            $kodeDokterSIMRS = $dokter->dokter_kode_medis;

            // Ambil data di tabel tc_antrian
            $result = DB::connection('mysph')
            ->table('tc_antrian')
            ->where('simrs_dokter_kode', $kodeDokterSIMRS)
            ->where('simrs_subs_kode', $input['kodepoli'])
            ->where('simrs_antrian_tgl', $tanggal)
            ->select("*")
            ->orderBy('ant_id', 'DESC')
            ->get();

            $totalantrean = count($result);
            $antreanpanggil = (int)$result->where('simrs_noreg', ' ')->first()->simrs_antrian_urut;
            $sisaantrean = $totalantrean - $antreanpanggil;
            $bpjs = $result->where('simrs_jaminan_kode', 'BJS01');
            $non = $result->where('simrs_jaminan_kode', '<>' , 'BJS01');
            $totalNon = count($non);
            $totalBPJS = count($bpjs);
            $sisakuotajkn = count($bpjs->where('simrs_noreg', ' '));
            $sisakuotanonjkn = count($non->where('simrs_noreg', ' '));

        }catch(\Illuminate\Database\QueryException $e){
            dd($e);
        }catch (\Exception $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'response' => (object)[
                // 'dd' => $result,
                'namapoli'=>  $layananNama,
                'namadokter'=> $dokter->dokter_nama_gelar,
                'totalantrean'=> $totalantrean,
                'sisaantrean'=> $sisaantrean,
                'antreanpanggil'=> $antreanpanggil,
                'sisakuotajkn'=> $sisakuotajkn,
                'kuotajkn'=> $totalBPJS,
                'sisakuotanonjkn'=> $sisakuotanonjkn,
                'kuotanonjkn'=> $totalNon,
                'keterangan'=> 'Status Antrean Poli ' .$layananNama
            ]
        ];
    }

    public function sisaAntrean($input){
        try {
            $rowAntrean = DB::connection('mysph')
                ->table('tc_antrian')
                ->where('simrs_antrian_kode', $input)
                ->first();

            $data = DB::connection('mysph')
                ->table('tc_antrian')
                ->where('simrs_antrian_tgl', $rowAntrean->simrs_antrian_tgl)
                ->where('simrs_dokter_kode', $rowAntrean->simrs_dokter_kode)
                ->orderBy('ant_id', 'DESC')
                ->get();

            $antreanpanggil = $data->where('simrs_noreg', ' ')->first()->simrs_antrian_urut;
            $totalantrean = count($data);
            $sisaantrean = $totalantrean - $antreanpanggil;

            //kalkulasi estimasi
            $estimasi_time = (int)$rowAntrean->simrs_antrian_urut * 10;
            $estimasi = Carbon::parse($estimasi_time);
            $estimasi = (int) ($estimasi->timestamp . "000");

        } catch (QueryException $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return (object)[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
        return (object)[
            'code' => 200,
            'response' => (object)[
                'nomorantrean'=>  $rowAntrean->simrs_antrian_urut,
                'namapoli'=> $rowAntrean->simrs_poli_nama,
                'namadokter'=> $rowAntrean->simrs_dokter_nama,
                'sisaantrean'=> $sisaantrean,
                'antreanpanggil'=> $antreanpanggil,
                'waktutunggu'=> $estimasi,
                'keterangan'=> 'Sisa Antrean'
            ]
        ];

    }

    public function cekKuota($layanan = null, $kodeDokterBPJS = null ,$tanggalBooking = null){
        $layanan = 'ANA';
        $kodeDokterBPJS = '8888296';
        $tanggalBooking = '2022-03-16';

        $data = DB::connection('mysph')
        ->table('tc_antrian')
        ->where('simrs_subs_kode', $layanan)
        ->where('simrs_antrian_tgl', $tanggalBooking)
        ->where('simrs_dokter_kode', $kodeDokterBPJS)
        ->select('*')
        ->get();

        $totaBPJS = count($data->where('simrs_jaminan_kode', 'BPJS01'));
        $totaNonBPJS = count($data->where('simrs_jaminan_kode', '<>', 'BPJS01'));


        return (object)[
            'kuotaBPJS' => $totaBPJS,
            'kuotaNonBPJS' => $totaNonBPJS,
        ];
    }

    public function cekDokterKodeRs($kodeDokterBPJS = null){
        $id = DB::connection('ta_dokter')
        // ->where('dokter_kode_bpjs', $kodeDokterBPJS)
        ->where('dokter_kode_bpjs', '8888235')
        ->select('dokter_kode_medis')
        ->first();

        return $id;
    }

    public function antreanSekarang($kdDokterSIMRS, $kdPoliSIMRS, $tanggal){
    }

    public function tarikAntrean(){
        $data_antrian = DB::connection('mysph')
            ->table('tc_antrian')
            // ->where('simrs_jaminan_kode', 'BJS01')
            ->where('simrs_antrian_kode', 'AN1554017A')
            ->select(
                '*'
                )
            ->orderBy('ant_id', 'desc')
            ->take(1)
            ->first();
            // dd($data_antrian);
            /* Output */
            // +"ant_id": "49573"
            // +"simrs_antrian_kode": "AN1553948A"
            // +"simrs_antrian_tgl": "2022-03-21"
            // +"simrs_antrian_urut": "13"
            // +"simrs_booking_kode": null
            // +"simrs_noreg": "RG01686956"
            // +"simrs_mr": "137130500216426"
            // +"simrs_nopolis": "0000283130739"
            // +"simrs_poli_kode": "RJ030"
            // +"simrs_poli_nama": "BEDAH PLASTIK"
            // +"simrs_subs_kode": "BDP"
            // +"simrs_dokter_kode": "8888097"
            // +"simrs_dokter_nama": "BENNY RAYMOND, DR, SP.BP"
            // +"simrs_jaminan_kode": "BJS01"
            // +"simrs_jaminan_nama": "BPJS KESEHATAN"
            // +"ant_jam_masuk": null
            // +"ant_jam_selesai": null
            // +"ant_batal": null


        /* Ambil kode dokter berdasarkan layanan dan kode dokter */
        $dokter =   DB::connection('mysph')
                    ->table('ta_dokter AS aa')
                    ->where('dokter_kode_medis', $data_antrian->simrs_dokter_kode)
                    ->where('dokter_praktek', 'Y')
                    ->select('*')
                    ->first();
                    /*  +"dokter_kode_medis": "8888097"
                        +"dokter_id_jadwal": "63"
                        +"dokter_nama": "Benni Raymond"
                        +"dokter_nama_gelar": "dr. Benni Raymond, Sp.BP-RE"
                        +"dokter_gelar_depan": "dr"
                        +"dokter_gelar_belakang": "Sp.BP-RE"
                        +"dokter_jekel": "L"
                        +"dokter_inisial": "DR BR"
                        +"dokter_kode_bpjs": "31816"
                        +"dokter_kode_simrs": "8888097"
                        +"bpjs_subs_kode": "BDP"
                        +"dokter_praktek": "Y" */

        // Ambil id dokter
        // $dokter_id = $dokter->pluck('dokter_id_jadwal');
        $data_pasien = DB::connection('simrs')
                ->table('tc_mr')
                ->where('fs_mr', $data_antrian->simrs_mr)
                ->first();
        dd($data_pasien);
        /**
         * WAKTU SIMRS : 1 - Minggu, 2 - Senin , ... , 7 - Sabtu
         * WAKTU PHP : 7 - Minggu, 1 - Senin, ... , 6 - Sabtu
         */
        $simrs_hari = (date('N', strtotime($data_antrian->simrs_antrian_tgl)) + 1) % 7;
        $simrs_hari = $simrs_hari == 0 ? 7 : $simrs_hari;

        // ambil jadwal di simrs berdasarkan kode dokter
        $data_jadwal_simrs = DB::connection('simrs')
                ->table('TA_JADWAL_DOKTER AS aa')
                ->where('aa.fs_kd_dokter', $data_antrian->simrs_dokter_kode)
                ->where('aa.fs_kd_layanan', $data_antrian->simrs_poli_kode)
                ->where('aa.fn_hari', $simrs_hari)
                // ->selectRaw("aa.fs_kd_dokter, aa.fs_jam_mulai, aa.fs_jam_selesai, aa.fs_kd_sesion_poli, aa.fn_max")
                ->select('*')
                ->first();

                /** Output */
                // +"FS_KD_DOKTER": "8888265"
                // +"fn_no_urut": "2"
                // +"FS_JAM_MULAI": "11:00"
                // +"FS_JAM_SELESAI": "14:00"
                // +"FS_KD_LAYANAN": "RJ040"
                // +"FN_HARI": "3"
                // +"FS_KD_SESION_POLI": "1"
                // +"FN_MAX": "50"
                // +"FN_LAMA": "0"
                // +"fb_tutup": "0"
                // +"FN_TAMBAHAN_JAM_SELESAI": "0"
                // +"FS_KD_FRUANG": " "
                // +"FN_NO_URUT_HEADER": "0"
                // +"FS_CATATAN": " "
                // +"FS_PREFIX_ANTRIAN": " "
                // +"FB_PUBLISH_HIDOK": "0"
                // +"fs_pola_antrian": " "
                // +"fb_publish_kiosk": "0"
                // +"fb_bpjs": "0"
                // +"CRTTGL": "2022-01-04"
                // +"CRTJAM": "21:33:12"
                // +"CRTIPA": "192.168.150.135\USER"
                // +"CRTVER": "11.6.6169"
                // +"CRTUSR": "HAMDI"
                // +"UPDTGL": "3000-01-01"
                // +"UPDJAM": "00:00:00"
                // +"UPDIPA": " "
                // +"UPDVER": " "
                // +"UPDUSR": " "


        // ambil jadwal dokter di aplikasi jadwal
        $jadwal_web = DB::connection('mysph')
            ->table('tb_jadwal AS aa')
            ->leftjoin('ta_dokter as bb', 'bb.dokter_id_jadwal','=', 'aa.dokter_id')
            ->where(function($query) use($data_antrian){
                $tgl = $data_antrian->simrs_antrian_tgl;
                $query->whereRaw("'" . $tgl . "' BETWEEN aa.jadwal_tanggal_berlaku AND aa.jadwal_tanggal_expired")
                ->orWhereRaw("'" . $tgl . "' < aa.jadwal_tanggal_berlaku");
            })
            ->where('aa.hari_id', $simrs_hari)
            ->where('aa.dokter_id', $dokter->dokter_id_jadwal)
            ->first();

            /**
             * Output
             */

            // +"jadwal_id": "2039"
            // +"dokter_id": "123"
            // +"hari_id": "3"
            // +"jadwal_status": "1"
            // +"jadwal_jam_masuk": "11:00:00"
            // +"jadwal_jam_keluar": "14:00:00"
            // +"jadwal_kuota": "0"
            // +"jadwal_kuota_bpjs": "0"
            // +"jadwal_kuota_umum": "0"
            // +"jadwal_tanggal_berlaku": "2021-12-22"
            // +"jadwal_tanggal_expired": "3000-01-01"
            // +"jadwal_stats": "1"
            // +"jadwal_keterangan": null
            // +"jadwal_ref": "1843"
            // +"dokter_kode_medis": "8888265"
            // +"dokter_id_jadwal": "123"
            // +"dokter_nama": "Dewi Elianora"
            // +"dokter_nama_gelar": "Drg. Dewi Elianora, Sp. KGA"
            // +"dokter_gelar_depan": "Drg"
            // +"dokter_gelar_belakang": "Sp. KGA"
            // +"dokter_jekel": "P"
            // +"dokter_inisial": "DR DN"
            // +"dokter_kode_bpjs": "392966"
            // +"dokter_kode_simrs": "8888265"
            // +"bpjs_subs_kode": "GPR"
            // +"dokter_praktek": "Y"

        $res = (object)[];
        $res->kodebooking = $data_antrian->simrs_antrian_kode;
        $res->jenispasien = $data_antrian->simrs_jaminan_kode == "BJS01" ? 'JKN' : 'NON JKN';
        $res->nomorkartu = $data_antrian->simrs_nopolis;
        $res->nomorkartu = $data_antrian->simrs_nopolis;

        dd($jadwal_web, $data_jadwal_simrs);
    }
}
