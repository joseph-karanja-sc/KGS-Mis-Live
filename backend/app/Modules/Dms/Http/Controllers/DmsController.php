<?php

namespace App\Modules\Dms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;

class DmsController extends Controller
{

    protected $user_id;
    protected $dms_id;
    protected $dms_user;
    protected $user_email;
    protected $dms_path;

    public function __construct()
    {

        $this->middleware(function ($request, $next) {
            $this->user_id = Auth::user()->id;
            $this->dms_id = Auth::user()->dms_id;
            $this->user_email = Auth::user()->email;
            $this->dms_path = getcwd().'/mis_dms/';
            return $next($request);

        });
    }

    public function index()
    {
        return view('dms::index');
    }

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
        /* {{{ */
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

    public function addContent($documentid, $version, $versioncomment)
    {
        $result = false;
        $upload_path = $this->dms_path . 'data/1048576/' . $documentid . '/';

        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $fileName = $_FILES['localfile']['name'];
        $tmpName = $_FILES['localfile']['tmp_name'];
        $ext = $this->getfile_extension($fileName);
        $checksum = $this->getChecksum($tmpName);
        $fileSize = $this->fileSize($tmpName);
        $fileType = '.' . $ext;
        $mimeType = $_FILES["localfile"]["type"];
        $filepath = $upload_path . $version . '.' . $ext . '';

        if (move_uploaded_file($tmpName, $filepath)) {

            $documentContent = array(
                'document' => $documentid,
                'version' => $version,
                'comment' => $versioncomment,
                'date' => date('Y-m-d'),
                'createdBy' => $this->dms_id,
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
            //an error in upploading document (tbldocuments table) roll back changes
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

    public function getSubModuleFolderID(Request $request)
    {
        try {
            $parent_folder_id = $request->input('parent_folder_id');
            $sub_module_id = $request->input('sub_module_id');
            $folder_id = getSubModuleFolderID($parent_folder_id, $sub_module_id);
            $res = array(
                'success' => true,
                'message' => 'Folder id retrieved!!',
                'folder_id' => $folder_id
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage(),
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage(),
            );
        }
        return response()->json($res);
    }

    public function addDocument(Request $req)
    {
        $folder_id = $req->input('folder_id');
        $version = 1;
        if (!validateisNumeric($folder_id)) {
            $resp = array(
                'success' => false,
                'message' => 'Problem encountered getting folder id!!'
            );
            return response()->json($resp);
        }
        if ($_FILES['localfile']['size'] == 0) {
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
                'name' => $req->name,
                'comment' => $req->comment,
                'date' => time(),
                'owner' => $this->dms_id,
                'folderList' => $pathPrefix,
                'folder' => $folder_id
            );

            $documentid = $this->saveRecordReturnId($data, 'tbldocuments');
            if (validateisNumeric($documentid)) {
                $resultAddDocumentContent = $this->addContent($documentid, $version, $req->versioncomment);

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
                            'userID' => $this->dms_id
                        );
                        $resstatuslog = $this->saveRecord($statusLogData, 'tbldocumentstatuslog');
                        if ($resstatuslog) {
                            $resp = array('success' => true, 'message' => 'document added Successfully');
                        } else {
                            $resp = array('success' => false, 'message' => 'An error occurred in adding document status log');
                        }
                    } else {
                        $resp = array('success' => false, 'message' => 'An error occurred in adding document status');

                    }

                } else {
                    $resp = array(
                        'success' => false,
                        'message' => 'An error occurred in adding document content'
                    );
                }

            } else {
                //an error is saving details to db (tbldocuments table) roll back changes
                $resp = array(
                    'success' => false,
                    'message' => 'An error occurred in saving document'
                );

            }

        }
        json_output($resp);
    }

    public function addDocumentNoFolderId(Request $req)
    {
        $parent_id = $req->input('parent_folder_id');
        $sub_module_id = $req->input('sub_module_id');
        $folder_id = getSubModuleFolderID($parent_id, $sub_module_id);
        if (!validateisNumeric($folder_id)) {
            $resp = array(
                'success' => false,
                'message' => 'Problem encountered getting folder id!!'
            );
            return response()->json($resp);
        }
        $version = 1;
        if ($_FILES['localfile']['size'] == 0) {
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
                'name' => $req->name,
                'comment' => $req->comment,
                'date' => time(),
                'owner' => $this->dms_id,
                'folderList' => $pathPrefix,
                'folder' => $folder_id
            );

            $documentid = $this->saveRecordReturnId($data, 'tbldocuments');
            if (validateisNumeric($documentid)) {
                $resultAddDocumentContent = $this->addContent($documentid, $version, $req->versioncomment);

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
                            'userID' => $this->dms_id
                        );
                        $resstatuslog = $this->saveRecord($statusLogData, 'tbldocumentstatuslog');
                        if ($resstatuslog) {
                            $resp = array('success' => true, 'message' => 'document added Successfully');
                        } else {
                            $resp = array('success' => false, 'message' => 'An error occurred in adding document status log');
                        }
                    } else {
                        $resp = array('success' => false, 'message' => 'An error occurred in adding document status');

                    }

                } else {
                    $resp = array(
                        'success' => false,
                        'message' => 'An error occurred in adding document content'
                    );
                }

            } else {
                //an error is saving details to db (tbldocuments table) roll back changes
                $resp = array(
                    'success' => false,
                    'message' => 'An error occurred in saving document'
                );
            }

        }
        json_output($resp);
    }


    public function addDocumentNoFolderIdForAssetLoss($parent_id,$sub_module_id,$dms_id,$name,$comment,$versioncomment)
    {
       
       
       
        $folder_id = getSubModuleFolderID($parent_id, $sub_module_id);
      
        if (!validateisNumeric($folder_id)) {
            $resp = array(
                'success' => false,
                'message' => 'Problem encountered getting folder id!!'
            );
            return $resp;
        }
        $version = 1;
        if ($_FILES['localfile']['size'] == 0) {
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
                'name' => $name,
                'comment' => $comment,
                'date' => time(),
                'owner' => $dms_id,
                'folderList' => $pathPrefix,
                'folder' => $folder_id
            );

            $documentid = $this->saveRecordReturnId($data, 'tbldocuments');
            if (validateisNumeric($documentid)) {
                $resultAddDocumentContent = $this->addContent($documentid, $version, $versioncomment);

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
                            'status' => 2, //document Released there were no approvers or approvers
                            'comment' => 'New document content submitted',
                            'date' => date('Y-m-d H:i:s'),
                            'userID' => $dms_id
                        );
                        $resstatuslog = $this->saveRecord($statusLogData, 'tbldocumentstatuslog');
                        if ($resstatuslog) {
                            $resp = array('success' => true, 'message' => 'document added Successfully');
                        } else {
                            $resp = array('success' => false, 'message' => 'An error occurred in adding document status log');
                        }
                    } else {
                        $resp = array('success' => false, 'message' => 'An error occurred in adding document status');

                    }

                } else {
                    $resp = array(
                        'success' => false,
                        'message' => 'An error occurred in adding document content'
                    );
                }

            } else {
                //an error is saving details to db (tbldocuments table) roll back changes
                $resp = array(
                    'success' => false,
                    'message' => 'An error occurred in saving document'
                );
            }

        }
        return $resp;
    }

}

