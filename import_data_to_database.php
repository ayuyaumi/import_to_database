class ImportDataController extends BaseController
{

	public function storeExternalStaff(){

		$client = new \GuzzleHttp\Client();
		$result = $client->request('GET', 'http://mdch.smsparkir.com/core/collections/enforcers?_params={%22select%22:%22_id%20id%20nric%20name%20address%20username%22}');

		$response = json_decode($result->getBody()->getContents());
		if($response->success == true){
			foreach ($response->data as $key => $v) {

				$staff = Staff::where('no_badan', $v->id)->where('roles_access','PenguatKuasa')->first();
				if(empty($staff)) {

					$encryptP = Hash::make('12345678');
					$profile_img = '/uploads/profile.jpg';
					
					$sl = Staff::create([
						'no_badan' => $v->id,
						'password' => $encryptP,
						'api_token' => '',
						'roles_access' => 'PenguatKuasa',
						'device' => '',
						'remember_token' => '',
						'last_login_at' => '',
						'last_login_ip'=> '',
						'authorized' => false,
						'token_firebase' => '',
					]);

					$detail = new StaffDetail();		
					$detail->full_name = $v->name;
					$detail->identity =  $v->nric;
					$detail->mobile = '-';
					$detail->gred = '-';
					$detail->no_badan = $v->id;
					$detail->roles_access = 'PenguatKuasa';
					$detail->profile_img = $profile_img;
					$detail->password = $encryptP;

					$sl->staffdetail()->save($detail);

					$department = Department::where('_id', '5d957899f3da686c08192026')->first();
					$detail->department()->attach($department);
				}
			}
		}
	}


	/**
     * Get Summon id from external controller.
     *
     * @return value
     */
	public function getReturnSummonId(){
		$client = new \GuzzleHttp\Client();
		$result = $client->request('GET', 'http://mdch1.sipadu.test:8080/sms_parkir_berbayar.json');

		$compound = Compound::all(); $temp = [];
		$response = json_decode($result->getBody()->getContents());
		if($response->success == true){
			foreach ($response->data as $key => $s) {

				$kpd = '';
				if(strlen($s->no) == 2){
					$kpd = 'SCM00'.$s->no;
				}elseif(strlen($s->no) == 3){
					$kpd = 'SCM0'.$s->no;
				}else{
					$kpd = 'SCM'.$s->no;
				}

				$com = Compound::where('_id', $s->_id)->orWhere('kpd', $kpd)->first();
				if(empty($com)){
					$temp[] = $s;
				}
			}
			return $response->data;
		}
	}

	public function storeExternalCompound(Request $request){
		
		$temp = array(); $cTemp = array();
		$summons = json_decode(file_get_contents('http://mdch1.sipadu.test:8080/sms_parkir-berbayar2.json'));

		foreach (array_chunk($summons->data, 300) as $key => $data) {
			foreach ($data as $key => $s) {

				$kpd = '';
				if(strlen($s->no) == 1)
					$kpd = 'SCM000'.$s->no;
				elseif(strlen($s->no) == 2){
					$kpd = 'SCM00'.$s->no;
				}elseif(strlen($s->no) == 3){
					$kpd = 'SCM0'.$s->no;
				}else{
					$kpd = 'SCM'.$s->no;
				}

				$com = Compound::where('_id', $s->_id)->orWhere('kpd', $kpd)->first();
				if(empty($com)){

					$section = str_replace("Seksyen ","", $s->violations[0]->name);
					$faulty = Faulty::with('DeedLaw')->where('sketr',$section)->first();

					if($s->issuedBy->id == "13"){
						$no_badan = '00186';
					}else if($s->issuedBy->id == "12"){
						$no_badan = '00185';
					}else if($s->issuedBy->id == "11"){
						$no_badan = '00184';
					}else if($s->issuedBy->id == "10"){
						$no_badan = '00183';
					}else{
						$no_badan = $s->issuedBy->id;
					}

					$staff = Staff::with('StaffDetail')->where('no_badan',$no_badan)->first();
					$department = Department::where('_id','5d957899f3da686c08192026')->first();

					if(!empty($faulty) && !empty($staff) && !empty($department)){

						do {
							$no_siri = date('yn',strtotime($s->createdAt)).'-'.$s->no;
						} while (ConfidentialFile::where("no_siri", "=", $no_siri)->first() instanceof ConfidentialFile);

						$carbonC = new Carbon($s->createdAt); 
						$carbonU = new Carbon($s->updatedAt);  
						$tarikh_bayar = new Carbon($s->status->updateHistories[0]->updateAt);

			            $com = new Compound();
			            $com->_id = new \MongoDB\BSON\ObjectID($s->_id);
			            $com->jenis = 'Parkir';
		                $com->kpd = $kpd;
		                $com->nama = '-';
		                $com->identity = '-';
		                $com->alamat = '-';
		                $com->no_plate = $s->vehicle->plateNo;
		                $com->no_cukai_jalan = $s->vehicle->roadTaxNo;
		                $com->jenis_kenderaan = $s->vehicle->type;
		                $com->model_kenderaan = $s->vehicle->model;
		                $com->warna_kenderaan = $s->vehicle->color;
		  			    $com->nama_kawasan = $s->location->section;
		                $com->nama_taman = $s->location->area;
		                $com->nama_jalan = $s->location->road;
		                $com->no_parking = $s->location->lot;
		                $com->catatan = '-';
		                $com->lokasi_kejadian = '-';
		                $com->latlong = '';
		                $com->jbkod = $department->_id;
		                $com->akta = $faulty->deed_law_id;
		                $com->seksyen_kesalahan = $faulty->_id;
		                $com->jumlah_asal_kompaun = $faulty->amount;
		                $com->jumlah_kemaskini_kompaun = '';
		                $com->dikeluarkan = $staff->_id;
		                $com->status = 'Berbayar';
		                $com->amount_payment = $s->status->paidAmount;
		  			    $com->amount_tunggakan = "0.00";
		                $com->receipt = '';
		  			    $com->tarikh_bayar = $tarikh_bayar->toDateTimeString();
		  			    $com->catatan_dari_admin = $s->status->remark;
		  			    $com->update_by = $s->status->updateHistories[0]->updateBy;
		                $com->modul = '03';
		                $com->penguatkuasa = '';
		                $com->created_at = $carbonC;
		                $com->updated_at = $carbonU;
		                $com->save();


		                $file = ConfidentialFile::create([
		                	'no_siri' => $no_siri,
		                ]);
		            	$file->compound()->save($com);

		            	$compound = Compound::with('ConfidentialFile','Attachment','CompoundInvestigation')->where('kpd', $kpd)->first();
		            	foreach ($s->pictures as $key => $d) {
		                    $attach = new Attachment();
		                    $attach->path = $d;
		                    $compound->attachment()->save($attach);
		                }

		                //Save History
		                $gDate = $compound->created_at->format('F Y');
			            $historyData = [
			                'tarikh_kumpulan' => $gDate,
			            ];
			            $subHistory = [
			                'no_siri' => $compound->ConfidentialFile->no_siri,
			                'tajuk' => "Penguatkuasa ".$staff->StaffDetail->full_name." mengeluarkan kompaun ".$kpd,
			                'huraian' => "Kompaun ".$kpd." telah dikeluarkan oleh penguatkuasa <a href='https://mdch.sipadu.my/main/staff/".$staff->_id."/profile'>".$staff->StaffDetail->full_name."</a> di bawah akta seksyen kesalahan [".$faulty->sketr."] ".$faulty->nama,
			            ];

			            $groupByDate = History::where('tarikh_kumpulan', $gDate)->first();
			            if(!empty($groupByDate)){
			                $groupByDate->subhistory()->create($subHistory);
			                $compound->ConfidentialFile->history()->attach($groupByDate);
			            }else{
			                $history = History::create($historyData);
			                $history->subhistory()->create($subHistory);
			                $compound->ConfidentialFile->history()->attach($history);
			            }

			            // Run Job
		            	dispatch(new UpdateCompoundPrice($kpd));
		            	dispatch(new ConvertToPDF($kpd, $staff->_id, $no_siri));

					}else {
						$temp[] = $s;
					}

				}else {
					$cTemp[] = $s;
				}
			}
		}

		return 'finished. Jumlah kompaun yg tidak dpt store: Wujud: '.count($cTemp). ' Data x lengkap: '.count($temp);
	}
}