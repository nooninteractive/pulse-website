const fs=require('fs'),path=require('path'),assert=require('assert/strict'),crypto=require('crypto');
const origin=process.env.PULSE_TEST_ORIGIN||'http://127.0.0.1:4192';
const fixture=process.argv[2];if(!fixture)throw Error('Provide Unity-exported fixture.pls');
let cookie='',csrf='';
async function request(action,body,override={}){
 const headers={Cookie:cookie,Origin:origin,'X-CSRF-Token':csrf,...(body&&!(body instanceof FormData)?{'Content-Type':'application/json'}:{}),...override};
 const response=await fetch(origin+'/api/pulse.php?action='+action,{method:body?'POST':'GET',headers,body:body&&!(body instanceof FormData)?JSON.stringify(body):body});
 const set=response.headers.getSetCookie();if(set.length)cookie=set[0].split(';')[0];
 const bytes=Buffer.from(await response.arrayBuffer());let data;try{data=JSON.parse(bytes.toString())}catch{data=null}if(data?.csrf)csrf=data.csrf;
 return {status:response.status,data,bytes,headers:response.headers};
}
const check=(r,n)=>{assert.equal(r.status,n,JSON.stringify(r.data));return r};
function upload(bytes){const form=new FormData();form.set('rights','yes');form.set('package',new Blob([bytes],{type:'application/octet-stream'}),'map.pls');return form}
function zipOne(name,text){const n=Buffer.from(name),b=Buffer.from(text);let crc=0xffffffff;for(const x of b){crc^=x;for(let j=0;j<8;j++)crc=(crc>>>1)^((crc&1)?0xedb88320:0)}crc=(crc^0xffffffff)>>>0;const h=Buffer.alloc(30),c=Buffer.alloc(46),e=Buffer.alloc(22);h.writeUInt32LE(0x04034b50);h.writeUInt16LE(20,4);h.writeUInt32LE(crc,14);h.writeUInt32LE(b.length,18);h.writeUInt32LE(b.length,22);h.writeUInt16LE(n.length,26);c.writeUInt32LE(0x02014b50);c.writeUInt16LE(20,4);c.writeUInt16LE(20,6);c.writeUInt32LE(crc,16);c.writeUInt32LE(b.length,20);c.writeUInt32LE(b.length,24);c.writeUInt16LE(n.length,28);e.writeUInt32LE(0x06054b50);e.writeUInt16LE(1,8);e.writeUInt16LE(1,10);e.writeUInt32LE(c.length+n.length,12);e.writeUInt32LE(h.length+n.length+b.length,16);return Buffer.concat([h,n,b,c,n,e])}
(async()=>{
 const bytes=fs.readFileSync(fixture),stamp=Date.now();
 check(await request('session'),200);check(await request('maps'),200);
 check(await request('upload_map',upload(bytes)),401);
 check(await request('register',{name:'MapAuthor'+stamp,email:'map'+stamp+'@example.test',password:'local-map-test-password-123'}),201);
 check(await request('upload_map',upload(bytes),{'X-CSRF-Token':'bad'}),403);
 check(await request('upload_map',upload(Buffer.from('not a zip'))),422);
 check(await request('upload_map',upload(zipOne('../escape.osu','osu file format v14'))),422);
 check(await request('upload_map',upload(zipOne('map.osu','osu file format v14\n[General]\nMode:0\nAudioFilename:missing.wav\n[Metadata]\nTitle:Missing audio\nArtist:Test\nCreator:Test\nVersion:Normal\n[HitObjects]\n100,100,1000,1,0,0:0:0:0:\n'))),422);
 const created=check(await request('upload_map',upload(bytes)),201);const id=created.data.id;
 const listed=check(await request('maps&q=Integration'),200);const map=listed.data.maps.find(m=>Number(m.id)===id);assert.ok(map);assert.equal(map.objects,3);assert.equal(map.artist,'Pulse Test');assert.equal(map.canDelete,true);assert.equal(map.creator,'Test Mapper');
 const download=check(await request('download_map&id='+id),200);assert.match(download.headers.get('content-disposition'),/attachment; filename="pulse-map-\d+\.pls"/);assert.equal(crypto.createHash('sha256').update(download.bytes).digest('hex'),crypto.createHash('sha256').update(bytes).digest('hex'));
 const wav=Buffer.alloc(8044);wav.write('RIFF');wav.writeUInt32LE(wav.length-8,4);wav.write('WAVEfmt ',8);wav.writeUInt32LE(16,16);wav.writeUInt16LE(1,20);wav.writeUInt16LE(1,22);wav.writeUInt32LE(8000,24);wav.writeUInt32LE(16000,28);wav.writeUInt16LE(2,32);wav.writeUInt16LE(16,34);wav.write('data',36);wav.writeUInt32LE(8000,40);
 const audio=new FormData();for(const[k,v]of Object.entries({title:'Audio compatibility',description:'Local test',genre:'Chill',bpm:'120',rights:'yes'}))audio.set(k,v);audio.set('audio',new Blob([wav],{type:'audio/wav'}),'test.wav');const audioId=check(await request('upload',audio),201).data.id;check(await request('media&id='+audioId),200);check(await request('delete_content',{id:audioId}),200);
 check(await request('logout',{}),200);check(await request('download_map&id='+id),200);
 check(await request('register',{name:'OtherMapper'+stamp,email:'other'+stamp+'@example.test',password:'local-map-test-password-456'}),201);
 check(await request('delete_map',{id}),404);check(await request('logout',{}),200);
 check(await request('login',{email:'map'+stamp+'@example.test',password:'local-map-test-password-123'}),200);
 check(await request('delete_map',{id}),200);check(await request('download_map&id='+id),404);
 const report='PASS: real local PHP/SQLite registration, CSRF rejection, unauthenticated upload rejection, malformed ZIP/path traversal/missing audio rejection, Unity .pls upload, catalog metadata, byte-identical public download, ownership isolation, deletion, existing audio upload/playback compatibility.';
 fs.writeFileSync(path.resolve(__dirname,'../maps-test-result.txt'),report+'\n');console.log(report);
})().catch(e=>{console.error(e);process.exitCode=1});
