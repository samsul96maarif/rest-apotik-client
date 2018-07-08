<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

  var $API ="";

  public function __construct(){
    parent::__construct();
    $this->load->model('home_model');
    $this->load->model('keranjang_model');

    $this->API = "http://localhost/apotik_rest/api/";
  }

  //cek apakah session sudah ada
  private function cekSession(){
    if(!$this->session->userdata('id_session'))
      $this->session->set_userdata(array('id_session' => $this->generateSession()));
  }

  private function generateSession(){
    $h = "";
    $k = "1234567890qwertyuiopasdfghjklzxcvbnm";
    for ($i=1; $i <= 40; $i++) { 
      $h .= $k[rand(0, strlen($k) - 1)];
    }
    return $h;
  }

  public function index(){
    $this->cekSession();
    $data['view_name'] = 'beranda';
    $this->load->view('home/index_view', $data);
  }

  public function obat($kode_obat = NULL){
    $this->cekSession();
    if($kode_obat == NULL){
      $data['view_name'] = 'daftar_obat';
      $data['obat'] = json_decode($this->curl->simple_get($this->API.'/obat'));

      $this->load->view('home/index_view', $data);
      
    } else {

      $data['view_name'] = 'detail_obat';
      $data['obat'] = json_decode($this->curl->simple_get($this->API.'/obat/'.$kode_obat));

      $this->load->view('home/index_view', $data);
    }
  }

  //cek pembelian
  public function cek(){
    $this->cekSession();
    if($this->input->post('cek')){
      $kode = $this->input->post('kode_pesan');

      $cek_kode = $this->curl->simple_post($this->API.'/konfirmasi/cekKode', array('kode' => $kode, 'identitas' => $identitas ), array(CURLOPT_BUFFERSIZE => 10));

      if($cek_kode > 0){
        $detail = json_decode($this->curl->simple_get($this->API.'/keranjang/infoPemesanan/'.$kode));

        $tgl = explode("-", $detail->tanggal);
        $new_tgl = "";
        switch($tgl[1]){
          case '01': $new_tgl = "Januari"; break;
          case '02': $new_tgl = "Februari"; break;
          case '03': $new_tgl = "Maret"; break;
          case '04': $new_tgl = "April";
          case '05': $new_tgl = "Mei"; break;
          case '06': $new_tgl = "Juni"; break;
          case '07': $new_tgl = "Juli"; break;
          case '08': $new_tgl = "Agustus"; break;
          case '09': $new_tgl = "September"; break;
          case '10': $new_tgl = "Oktober"; break;
          case '11': $new_tgl = "November"; break;
          case '12': $new_tgl = "Desember"; break;
        }

        $date = str_split($tgl[2]);
        $tgl[2] = $date[0] == '0' ? $date[1] : $date[0].$date[1];

        $tgl = $tgl[2] ." ". $new_tgl ." ". $tgl[0];

        //menampilkan detail
        $this->session->set_flashdata('kode_pesan', $kode);
        $this->session->set_flashdata('nama', $detail->nama);
        $this->session->set_flashdata('harga', 'Rp '. number_format($detail->harga, 0, ',', '.'));
        $this->session->set_flashdata('tanggal', $tgl);
        $this->session->set_flashdata('status', $detail->status);
        $this->session->set_flashdata('detail', TRUE);

        $items = json_decode($this->curl->simple_get($this->API.'/keranjang/detailPemesanan/'.$kode));

        $this->session->set_flashdata('items', $items);

        redirect(site_url('cek'));
      }
      else {
        $message = '<div class="text-danger lead" style="padding-top: 10px">Tidak ditemukan kode pemesanan: <strong>'. $kode .'</strong></div>';
        $this->session->set_flashdata('msg', $message);
        redirect(site_url('cek'));
      }
    }
    else {
      $data['view_name'] = 'cek';

      $data = array(
        'view_name' => 'cek',
        'message'   => $this->session->flashdata('msg'),
        'detail'    => $this->session->flashdata('detail'),
        'kode_pesan'=> $this->session->flashdata('kode_pesan'),
        'nama'      => $this->session->flashdata('nama'),
        'harga'     => $this->session->flashdata('harga'),
        'tanggal'   => $this->session->flashdata('tanggal'),
        'status'    => $this->session->flashdata('status'),
        'items'     => $this->session->flashdata('items')
      );

      $this->load->view('home/index_view', $data);
    }
  }

  public function konfirmasi(){
    $this->cekSession();

    if($this->input->post('konfirmasi')){
      $kode_pesan = $this->input->post('kode_pesan');
      $identitas  = $this->input->post('identitas');

      $cek_kode = $this->curl->simple_post($this->API.'/konfirmasi', array('kode_pesan' => $kode_pesan, 'identitas' => $identitas ), array(CURLOPT_BUFFERSIZE => 10));

      if($cek_kode > 0){
        $pemesanan = json_decode($this->curl->simple_get($this->API.'/keranjang/cekStatus/'.$kode_pesan.'/'.$identitas));

        $user_info = json_decode($this->curl->simple_get($this->API.'/keranjang/userInfo/'.$pemesanan->id_pemesan));

        $user_info = $this->home_model->getUserInfo($pemesanan->id_pemesan);

        if($pemesanan->status == 'L'){
          $date = explode("-", $pemesanan->konfirmasi);
          $date = $date[2] ."/". $date[1] ."/". $date[0];
          $message = '<p class="alert alert-success text-center">Pemesanan <strong>'. $kode_pesan .'</strong> telah dikonfirmasi sebelumnya pada '. $date .' oleh '. $user_info->nama .'</p>';
        }
        else {
          if($this->curl->simple_post($this->API.'/keranjang/updateStatus', array('kode' => $kode_pesan), array(CURLOPT_BUFFERSIZE => 10)))
            $message = '<p class="alert alert-success text-center">Pemesanan <strong>'. $kode_pesan .'</strong> berhasil dikonfirmasi atas nama '. $user_info->nama .'</p>';
          else
            $message = '<p class="alert alert-danger text-center"><b>Terjadi kesalahan</b>, konfirmasi pembelian gagal.</p>';
        }

        $this->session->set_flashdata('msg', $message);
        redirect(site_url('konfirmasi'));
      }
      else {
        $message = '<p class="alert alert-danger text-center">Tidak ditemukan kode pemesanan: <strong>'. $kode_pesan .'</strong></p>';
        $this->session->set_flashdata('msg', $message);
        
        redirect(site_url('konfirmasi'));
      }
    }
    else {
      $data['view_name'] = 'konfirmasi';
      $data['message'] = $this->session->flashdata('msg');

      $this->load->view('home/index_view', $data);
    }
  }

  public function tambah_keranjang($kode){
    $this->cekSession();

    $cek_keranjang = json_decode($this->curl->simple_get($this->API.'/keranjang/cek/'.$kode.'/'.$this->session->userdata('id_session')));

    if($cek_keranjang > 0){
      if(!$this->curl->simple_post($this->API.'/keranjang', array('kode' => $kode, 'id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10)))
        echo "Gagal menambahkan item kedalam keranjang";
    }
    else {
      if(! $this->curl->simple_put($this->API.'/keranjang', array('kode' => $kode, 'id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10)))
        echo "Gagal menambahkan item kedalam keranjang";
    }

    redirect(site_url('beli'));
  }

  public function beli(){
    $this->cekSession();

    if($this->input->post('batal_beli')){
      if(!$this->curl->simple_delete($this->API.'/keranjang', array('id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10)))
        echo "Gagal melakukan operasi pembatalan pembelian";
    }
    
    //jika menekan tombol proses beli
    if($this->input->post('proses_beli')){
      $nama      = $this->input->post('nama');
      $identitas = $this->input->post('identitas');

      $cek_identitas = json_decode($this->curl->simple_post($this->API.'/beli/checkIdentity', array('identitas' => $identitas), array(CURLOPT_BUFFERSIZE => 10)));

      if($cek_identitas == 0){
        if(! $this->curl->simple_put($this->API.'/keranjang/insertPembeli', array('nama' => $nama, 'identitas' => $identitas), array(CURLOPT_BUFFERSIZE => 10)))
          echo "Maaf atas ketidaknyamanannya karena transaksi mengalami kegagalan. Coba beberapa saat lagi";
      }

      $pembeli = json_decode($this->curl->simple_get($this->API.'/keranjang/pembeli/'.$identitas));

      $kode_pesan = $this->curl->simple_put($this->API.'/keranjang/insertPemesanan', array('identitas' => $identitas, 'id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10));

      if(!$this->curl->simple_put($this->API.'/keranjang/insertDetailPemesanan', array('kode_pesan' => $kode_pesan, 'id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10))){
        echo "Maaf atas ketidaknyamanannya karena transaksi mengalami kegagalan. Coba beberapa saat lagi";
      }

      //hapus semua data yang ada di keranjang
      $this->curl->simple_delete($this->API.'/keranjang', array('id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10));

      $new_kode_pesan = "";
      foreach (str_split($kode_pesan) as $val) {
        $new_kode_pesan .= $val ." ";
      }

      $pesanFlash  = '<h1>Terima Kasih '. $pembeli->nama .'</h1>';
      $pesanFlash .= '<h2>Kode pemesanan Anda adalah: <mark>'. $new_kode_pesan .'</mark></h2>';
      $pesanFlash .= '<p>Silahkan lakukan pembayaran kemudian konfirmasi.</p>';
      $this->session->set_flashdata('msg', $pesanFlash);
      redirect(site_url('beli'));
    }
    
    else {
      $data['view_name'] = 'beli';
      $data['keranjang'] = json_decode($this->curl->simple_get($this->API.'/keranjang/'.$this->session->userdata('id_session')));
      $data['keranjang_info'] = json_decode($this->curl->simple_get($this->API.'/keranjang/info/'.$this->session->userdata('id_session')));

      $data['message'] = $this->session->flashdata('msg');

      $this->load->view('home/index_view', $data);
    }
  }

  public function update_jumlah(){
    $kode   = $this->input->post('kode_obat');
    $jumlah = $this->input->post('jumlah');

    $update =  $this->curl->simple_post($this->API.'/keranjang/updateItem', array('kode' => $kode, 'jumlah' => $jumlah, 'id' => $this->session->userdata('id_session')), array(CURLOPT_BUFFERSIZE => 10));

    $subtotal = json_decode($this->curl->simple_get($this->API.'/keranjang/keranjangSubTotal/'.$kode.'/'.$this->session->userdata('id_session')));

    $total    =  json_decode($this->curl->simple_get($this->API.'/keranjang/keranjangInfo/'.$this->session->userdata('id_session')));

    $data = array(
      'subtotal' => number_format($subtotal->subtotal, 0, ',', '.'),
      'total' => number_format($total->total, 0, ',', '.')
    );

    echo json_encode($data);
  }

  public function del_item(){
    $kode = $this->input->post('kode_obat');
    $id = $this->session->userdata('id_session');

    if($this->curl->simple_delete($this->API.'/keranjang/deleteItem/', array('kode'=>$kode, 'id'=>$id), array(CURLOPT_BUFFERSIZE => 10))){
      $total= json_decode($this->curl->simple_get($this->API.'/keranjang/info/'.$this->session->userdata('id_session')));
      $data['status'] = true;
      $data['total']  = number_format($total->total, 0, ',', '.');
    }
    else{
      $data['status'] = false;
    }

    echo json_encode($data);
  }

}
