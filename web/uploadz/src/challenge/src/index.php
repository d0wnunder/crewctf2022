<?php
 function create_temp_file($temp,$name){
    $file_temp = "storage/app/temp/".$name;
    copy($temp,$file_temp);
    
    return $file_temp;
  }
  function gen_uuid($length=6) {
    $keys = array_merge(range('a', 'z'), range('A', 'Z'));
    for($i=0; $i < $length; $i++) {
        $key .= $keys[array_rand($keys)];
        
    }

    return $key;
}
  function move_upload($source,$des){
    $name = gen_uuid();
    $des = "storage/app/uploads/".$name.$des;
    copy($source,$des);
    sleep(1);// for loadblance and anti brute
    unlink($source);
    return $des;
  }
  if (isset($_FILES['uploadedFile']))
  {
    // get details of the uploaded file
    $fileTmpPath = $_FILES['uploadedFile']['tmp_name'];
    $fileName = basename($_FILES['uploadedFile']['name']);
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    


   
    $dest_path = $uploadFileDir . $newFileName;
    $file_temp = create_temp_file($fileTmpPath, $fileName);
    echo "your file in ".move_upload($file_temp,$fileName);
    
  }
  if(isset($_GET["clear_cache"])){
    system("rm -r storage/app/uploads/*");
  }
?>
<form action="/" method="post" enctype="multipart/form-data">
Select image to upload: <input type="file" name="uploadedFile" id="fileToUpload"> 
<input type="submit" value="Upload Image" name="submit"> </form>

