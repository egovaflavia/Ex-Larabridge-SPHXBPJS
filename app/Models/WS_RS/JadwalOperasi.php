<?php

namespace App\Models\WS_RS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JadwalOperasi extends Model
{
    /**
     * Ambil data jadwal operasi
     *
     *
     * @param Date $tglAwal
     * @param Date $tglAkhir
     * @return Data
     */
    public function jadwalOperasi($tglAwal, $tglAkhir){
        $data = DB::connection('mysph')
                ->table('tb_jadwal_operasi')
                ->select(
                    'tb_jadwal_operasi.op_kode AS kodebooking',
                    'tb_jadwal_operasi.op_ren_tgl AS tanggaloperasi',
                    'tb_jadwal_operasi.op_tindakan AS jenistindakan',
                    // 'tb_jadwal_operasi.bpjs_subs_kode AS kodepoli',
                    // 'tb_jadwal_operasi.bpjs_subs_nama AS namapoli',
                    'tb_jadwal_operasi.op_terlaksana AS terlaksana',
                    'tb_jadwal_operasi.op_pasien_polis AS nopeserta',
                    'tb_jadwal_operasi.updated_at AS lastupdate')
                ->whereBetween('tb_jadwal_operasi.op_ren_tgl', [$tglAwal, $tglAkhir])
                ->where('tb_jadwal_operasi.op_status', '<>' ,'OD')
                ->get();
        return $data;
    }

    /**
     * Ambil jadwal operasi pasien
     *
     * @param string $nopeserta
     * @return void
     */
    public function jadwalOperasiPasien($nopeserta){
        $data = DB::connection('mysph')
                ->table('tb_jadwal_operasi')
                ->select(
                    'tb_jadwal_operasi.op_kode AS kodebooking',
                    'tb_jadwal_operasi.op_ren_tgl AS tanggaloperasi',
                    'tb_jadwal_operasi.op_tindakan AS jenistindakan',
                    /** Revisi Egova */
                    // 'tb_jadwal_operasi.bpjs_subs_kode AS kodepoli',
                    // 'tb_jadwal_operasi.bpjs_subs_nama AS namapoli',
                    'tb_jadwal_operasi.op_terlaksana AS terlaksana'
                    )
                ->where('tb_jadwal_operasi.op_pasien_polis', $nopeserta)
                ->where('tb_jadwal_operasi.op_terlaksana', 0)
                ->where('tb_jadwal_operasi.op_ren_tgl', '>=', date('Y-m-d'))
                ->get();
        return $data;
    }
}
