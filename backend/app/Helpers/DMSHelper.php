<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 11/15/2017
 * Time: 4:18 PM
 */

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use GuzzleHttp\Client as Client;
use App\User;
use Illuminate\Http\Request;

class DMSHelper
{
    protected $user_id;
    protected $dms_user;
    protected $user_email;
    protected $dms_path;

    public function __construct()
    {
        // $this->middleware(function ($request, $next) {
        $this->user_id = \Auth::user()->id;
        $this->dms_user = \Auth::user()->dms_id;
        $this->user_email = aes_decrypt(\Auth::user()->email);
        $this->dms_path = getcwd() . '/mis_dms/';
        //  return $next($request);
        //});
    }

    //todo
    //Create parent folders..their parents are the main modules
    //should only be created once
    static function createDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner)
    {
        $dms_db = DB::connection('dms_db');
        $folder_name = str_replace("/", '-', $name);
        $params = array(
            'name' => $folder_name,
            'parent' => $parent_folder,
            'folderList' => self::createFolderList($parent_folder),
            'comment' => $comment,
            'date' => time(),
            'owner' => $owner,
            'inheritAccess' => 1,
            'defaultAccess' => 1,
            'sequence' => 0,
            'module_id' => 0
        );
        $folder_id = $dms_db->table('tblfolders')
            ->insertGetId($params);
        return $folder_id;
    }

    static function createDMSModuleFolders($parent_id, $module_id, $owner)
    {
        try {
            $qry = DB::table('mis_dms_modules')
                ->where('parent_id', $module_id);
            $data = $qry->get();
            $params = array();
            foreach ($data as $datum) {
                $name = aes_decrypt($datum->name);
                $description = aes_decrypt($datum->description);
                $params[] = array(
                    'name' => $name,
                    'parent' => $parent_id,
                    'folderList' => self::createFolderList($parent_id),
                    'comment' => $description,
                    'date' => strtotime(date('Y/m/d H:i:s')),
                    'owner' => $owner,
                    'inheritAccess' => 1,
                    'defaultAccess' => 1,
                    'sequence' => 0,
                    'module_id' => $datum->id
                );
            }
            $dms_db = DB::connection('dms_db');
            $dms_db->table('tblfolders')
                ->insert($params);
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    static function getParentFolderID($table, $parent_record_id)
    {
        $parent_folder_id = DB::table($table)
            ->where('id', $parent_record_id)
            ->value('folder_id');
        return $parent_folder_id;
    }

    static function getSubModuleFolderID($parent_folder_id, $sub_module_id)
    {
        $where = array(
            'parent' => $parent_folder_id,
            'module_id' => $sub_module_id
        );
        $folder_id = '';
        try {
            $dms_db = DB::connection('dms_db');
            $folder_id = $dms_db->table('tblfolders')
                ->where($where)
                ->value('id');
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $folder_id;
    }

    static function getSubModuleFolderIDWithCreate($parent_folder_id, $sub_module_id, $owner)
    {
        $where = array(
            'parent' => $parent_folder_id,
            'module_id' => $sub_module_id
        );
        $folder_id = '';
        try {
            $dms_db = DB::connection('dms_db');
            $checker = $dms_db->table('tblfolders')
                ->where($where)
                ->first();
            if (!is_null($checker)) {
                $folder_id = $checker->id;
            } else {
                $sub_module_details = DB::table('mis_dms_modules')
                    ->where('id', $sub_module_id)
                    ->first();
                if (!is_null($sub_module_details)) {
                    $params = array(
                        'name' => aes_decrypt($sub_module_details->name),
                        'parent' => $parent_folder_id,
                        'folderList' => self::createFolderList($parent_folder_id),
                        'comment' => aes_decrypt($sub_module_details->description),
                        'date' => strtotime(date('Y/m/d H:i:s')),
                        'owner' => $owner,
                        'inheritAccess' => 1,
                        'defaultAccess' => 1,
                        'sequence' => 0,
                        'module_id' => $sub_module_details->id
                    );
                    $folder_id = $dms_db->table('tblfolders')
                        ->insertGetId($params);
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return $folder_id;
    }

    static function createFolderList($parent_id)
    {
        $dms_db = DB::connection('dms_db');
        $prev_folder_list = $dms_db->table('tblfolders')
            ->where('id', $parent_id)
            ->value('folderList');
        if ($prev_folder_list == '') {
            $curr_folder_list = ':1:' . $parent_id . ':';
        } else {
            $curr_folder_list = $prev_folder_list . $parent_id . ':';
        }
        return $curr_folder_list;
    }

    //hiram code on the dms functionalities
    static function authDms($usr_name)
    {

        $url = Config('constants.dms.dms_url');
        $client = new Client();
        $res = $client->request('POST', $url . 'login', [
            'form_params' => [
                'user' => $usr_name,
                'pass' => $usr_name,
                'attributes' => ''

            ]
        ]);
        $success = false;
        $data = array('success' => false, 'user_id' => '');
        if ($res->getStatusCode() == 200) {
            $res->getBody()->rewind();
            $response = json_decode((string)$res->getBody());
            $success = $response->success;
            $data = array('success' => $success, 'user_id' => $response->data);

        }
        return $data;

    }

    static function dms_createFolder($parent_folder, $name, $comment, $user_email)
    {
        $folder_id = '';
        $folder_name = str_replace("/", '-', $name);
        $url = Config('constants.dms.dms_url');
        $client = new Client();
        $res = $client->request('POST', $url . 'folder/' . $parent_folder . '/createfolder', [
            'form_params' => [
                'name' => $folder_name,
                'comment' => $comment,
                'user_id' => $user_email,
                'attributes' => ''
            ]
        ]);

        if ($res->getStatusCode() == 200) {
            $response = json_decode((string)$res->getBody());
            $folder_id = $response->data->id;
        }
        return $folder_id;
    }

    static function dms_FolderDocuments($folder_id, $user_email)
    {
        $url = Config('constants.dms.dms_url');
        $client = new Client();
        $res = $client->request('GET', $url . 'folder/' . $folder_id . '/document', [
            'form_params' => [
                'user_id' => $user_email
            ]
        ]);
        $documents = array();
        if ($res->getStatusCode() == 200) {
            $response = json_decode((string)$res->getBody());
            $documents = $response->results;
        }
        return $documents;
    }

    static function check_DmsFolderDocuments($folder_id, $user_email)
    {
        $sql = DB::connection('dms_db')->table('tbldocuments')
            ->where(array('folder' => $folder_id))
            ->count();
        $resp = false;
        if ($sql > 0) {
            $resp = true;
        }
        //print_r($resp);
        return $resp;
    }
    //end code

    //extensive DMS functions
    public function updateDocumentSequence($parent, $order_no)
    {
        //check if the order_no exists
        $where = array('folder' => $parent, 'order_no' => $order_no);
        $sql = DB::connection('dms_db')->table('tbldocuments')
            ->where($where)
            ->count();
        $return = false;
        if ($sql > 0) {
            $resp = DB::connection('dms_db')->table('tbldocuments')->where($where)->update(array('order_no' => $order_no + 1));
            if ($resp) {
                $return = true;
            }
        }
        return $return;
    }

    public function saveRecordReturnId($data, $table)
    {
        $insert_id = DB::connection('dms_db')->table($table)->insertGetId($data);
        if (validateisNumeric($insert_id)) {
            return $insert_id;
        } else {
            return false;
        }
    }

    public function saveRecord($data, $table)
    {
        $success = DB::connection('dms_db')->table($table)->insert($data);
        if ($success) {
            return true;
        } else {
            return false;
        }
    }

    public function getfile_extension($fileName)
    {
        $fileName_arr = explode('.', $fileName);
        //count taken (if more than one . exist; files like abc.fff.2013.pdf
        $file_ext_count = count($fileName_arr);
        //minus 1 to make the offset correct
        $cnt = $file_ext_count - 1;
        // the variable will have a value pdf as per the sample file name mentioned above.
        $ext = $fileName_arr[$cnt];
        return $ext;
    }

    public function fileSize($file)
    {
        if (!$a = fopen($file, 'r'))
            return false;
        fseek($a, 0, SEEK_END);
        $filesize = ftell($a);
        fclose($a);
        return $filesize;
    }

    public function format_filesize($size, $sizes = array('Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'))
    {
        if ($size == 0)
            return ('0 Bytes');
        return (round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $sizes[$i]);
    }

    public function parse_filesize($str)
    {
        preg_replace('/\s\s+/', ' ', $str);
        if (strtoupper(substr($str, -1)) == 'B') {
            $value = (int)substr($str, 0, -2);
            $unit = substr($str, -2, 1);
        } else {
            $value = (int)substr($str, 0, -1);
            $unit = substr($str, -1);
        }
        switch (strtoupper($unit)) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
                break;
            case 'M':
                return $value * 1024 * 1024;
                break;
            case 'K':
                return $value * 1024;
                break;
            default;
                return $value;
                break;
        }
        return false;
    }

    public function getParent($folderId)
    {
        if ($folderId == 1) {
            return false;
        } else {
            $sql = DB::connection('dms_db')->table('tblfolders')->select('parent')->where(array('id' => $folderId))->value('parent');

            if (validateisNumeric($sql)) {
                return $sql;
            } else {
                return false;
            }
        }
    }

    public function addContent($documentid, $version, $versioncomment, $file_name)
    {
        $result = false;
        $upload_path = $this->dms_path . 'data/1048576/' . $documentid . '/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        $fileName = $_FILES[$file_name]['name'];
        $tmpName = $_FILES[$file_name]['tmp_name'];
        $ext = $this->getfile_extension($fileName);
        $checksum = $this->getChecksum($tmpName);
        $fileSize = $this->fileSize($tmpName);
        $fileType = '.' . $ext;
        $mimeType = $_FILES[$file_name]["type"];
        $filepath = $upload_path . $version . '.' . $ext . '';
        if (move_uploaded_file($tmpName, $filepath)) {
            $documentContent = array(
                'document' => $documentid,
                'version' => $version,
                'comment' => $versioncomment,
                'date' => date('Y-m-d'),
                'createdBy' => $this->dms_user,
                'dir' => $documentid . '/',
                'orgFileName' => $fileName,
                'fileType' => $fileType,
                'mimeType' => $mimeType,
                'fileSize' => $fileSize,
                'checksum' => $checksum
            );
            //save data in database
            if ($this->saveRecord($documentContent, 'tbldocumentcontent')) {
                $result = true;
            } else {
                $result = false;
            }
        } else {
            //an error in uploading document (tbldocuments table) roll back changes
            echo "Error in uploading file";
            exit();
        }
        return $result;

    }

    public function getChecksum($file)
    {
        return md5_file($file);

    }

    public function getPath($folderId)
    {
        $path = array();
        $parent = $folderId;
        if ($parent == 0) {
            array_unshift($path, $folderId);
        } else {
            do {
                array_unshift($path, $parent);
            } while ($parent = $this->getParent($parent));
        }
        return $path;
    }

    public function addDocument($doc_name, $doc_comment, $file_name, $folder_id, $versioncomment = '', $is_arrayreturn = null)
    {
        $version = 1;
        $res = false;
        if ($_FILES[$file_name]['size'] == 0) {
            $resp = array('success' => false, 'message' => 'uploading an empty file. upload is cancelled');
        } else {
            // Set the folderList of the document
            $pathPrefix = "";
            $path = $this->getPath($folder_id);
            foreach ($path as $f) {
                $pathPrefix .= ":" . $f;
            }
            if (strlen($pathPrefix) > 1) {
                $pathPrefix .= ":";
            }
            //get form values
            $data = array(
                'name' => $doc_name,
                'comment' => $doc_comment,
                'date' => time(),
                'owner' => $this->dms_user,
                'folderList' => $pathPrefix,
                'folder' => $folder_id
            );
            $documentid = $this->saveRecordReturnId($data, 'tbldocuments');
            if (validateisNumeric($documentid)) {
                $resultAddDocumentContent = $this->addContent($documentid, $version, $versioncomment, $file_name);
                if ($resultAddDocumentContent) {
                    //add document status
                    $statusData = array(
                        'documentID' => $documentid,
                        'version' => $version
                    );
                    $statusID = $this->saveRecordReturnId($statusData, 'tblDocumentStatus');
                    if (is_numeric($statusID)) {
                        $statusLogData = array(
                            'statusID' => $statusID,//draft docuemnt pending approvals
                            'status' => 1, //document Released there were no approvers or approvers
                            'comment' => 'New document content submitted',
                            'date' => date('Y-m-d H:i:s'),
                            'userID' => $this->dms_user
                        );
                        $resstatuslog = $this->saveRecord($statusLogData, 'tbldocumentstatuslog');
                        if ($resstatuslog) {
                            $res = true;
                            $resp = array(
                                'document_id' => $documentid,
                                'success' => true,
                                'message' => 'document added Successfully!!'
                            );
                        } else {
                            $res = false;
                            $resp = array(
                                'document_id' => '',
                                'success' => false,
                                'message' => 'An error occurred in adding document status log!!'
                            );
                        }
                    } else {
                        $res = false;
                        $resp = array(
                            'document_id' => '',
                            'success' => false,
                            'message' => 'An error occurred in adding document status!!'
                        );
                    }
                } else {
                    $res = false;
                    $resp = array(
                        'success' => false,
                        'document_id' => '',
                        'message' => 'An error occurred in adding document content!!'
                    );
                }
            } else {
                $res = false;
                $resp = array(
                    'success' => false,
                    'message' => 'An error occurred in saving document'
                );
            }
        }
        if ($is_arrayreturn == 1) {
            return $resp;
        } else {
            return $documentid;
        }
    }
    //job
    static function createAssetDMSParentFolder($parent_folder, $module_id, $name, $comment, $owner)
    {
        $dms_db = DB::connection('dms_db');
        $folder_name = str_replace("/", '-', $name);
        $params = array(
            'name' => $folder_name,
            'parent' => $parent_folder,
            'folderList' => self::createFolderList($parent_folder),
            'comment' => $comment,
            'date' => time(),
            'owner' => $owner,
            'inheritAccess' => 1,
            'defaultAccess' => 1,
            'sequence' => 0,
            'module_id' => $module_id
        );
        $folder_id = $dms_db->table('tblfolders')
            ->insertGetId($params);
        return $folder_id;
    }

    static function createAssetRegisterDMSModuleFolders($parent_id, $module_id,$sub_module_id,$owner)
    {
        try {
            $qry = DB::table('mis_dms_modules')
                ->where('parent_id', $module_id)
                ->where('id',$sub_module_id);
            $data = $qry->get();
            $params = array();
            foreach ($data as $datum) {
                $name = aes_decrypt($datum->name);
                $description = aes_decrypt($datum->description);
                $params[] = array(
                    'name' => $name,
                    'parent' => $parent_id,
                    'folderList' => self::createFolderList($parent_id),
                    'comment' => $description,
                    'date' => strtotime(date('Y/m/d H:i:s')),
                    'owner' => $owner,
                    'inheritAccess' => 1,
                    'defaultAccess' => 1,
                    'sequence' => 0,
                    'module_id' => $datum->id
                );
            }
            $dms_db = DB::connection('dms_db');
            $dms_db->table('tblfolders')
                ->insert($params);
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }


}