
<?php

class ScanDirectory{
  
  private $path;
  private $filterExt;
  private $excludeExt;
  private $filterDir;
  
  private $text;
  private $markup;
  private $json;
  
  private $iconsPath;
  private $pad;
  
  private $exts;
  private $imageExts;
  private $musicExts;
  private $videoExts;
  
  public function __construct(){
    define("nl","\n");
    $this->iconsPath="./lib/images/";                       // filetype icons in the 'export/tree'
    $this->pad="    ";
    
    $this->formatPath();
    
    $this->filterExt=$this->getFilters('filter_ext');       
    $this->excludeExt=$this->getFilters('exclude_ext');
    $this->filterDir=$this->getFilters('filter_dir');
    
    $this->exts=array(
      "chm",
      "css",
      "djvu",
      "dll",
      "doc",
      "exe",
      "html",
      "iso",
      "js",
      "msi",
      "pdf",
      "php",
      "psd",
      "rar",
      "txt",
      "xls",
      "xml",
      "xpi",
      "zip",
    );
    
    $this->imageExts=array(
      "png",
      "gif",
      "jpg",
      "jpeg",
      "tiff",
      "bmp",
    );
    
    $this->musicExts=array(
      "mp3",
      "wav",
      "ogg",
      "alac",
      "flac",
    );
    
    $this->videoExts=array(
      "mkv",
      "flv",
      "vob",
      "avi",
      "wmv",
      "mov",
      "mp4",
      "mpg",
      "mpeg",
      "3gp",
    );
    
    $this->text=[];
    $this->markup=[];
    
    // return;
    
    $this->processData();                                   // << Start point >>
  }
  
  /*
   * Scans the directory
   * Outputs the result as text
   * Exports result if checkboxes are selected
   */  
  public function processData() {
    $json=$this->fullScan($this->path);
    $text=$this->wrapText();
    if(!$text) echo "No Data!";
    echo $text;
    
    if(isset($_POST['export_tree'])){                       // Export as tree to view with jsTree jQuery plugin (creates .json and .html files in the 'export')
      $this->json=json_encode($json);
      $this->exportTree();
    }
    
    if(isset($_POST['export_text'])){                       // Export simple text with space paddings (creates .txt in the searched directory)
      $text=join("\n",$this->text);
      
      $exportPath='export/text/';
      if(!file_exists($exportPath))
        mkdir($exportPath);
      
      $ext=".txt";
      $filename=$this->getExportName($ext);
      
      $file = fopen($exportPath.$filename, "w");              
      fwrite($file,$text);
      fclose($file);
    }
    
    if(isset($_POST['export_markup'])){                     // Export text with 'dir/file' <span> markup to .html file (creates .html in the searched directory)
      $markup=$this->wrapMarkup();
      
      $exportPath='export/markup/';
      if(!file_exists($exportPath))
        mkdir($exportPath);
      
      $ext=".html";
      $filename=$this->getExportName($ext);
      
      $file = fopen($exportPath.$filename, "w");              
      fwrite($file, $markup);
      fclose($file);
    }
  }
  
  /*
   * Recursive scans all subdirectories
   */
  public function fullScan($dir, $level=-1){               
    $json=[];
    
    $data = scandir($dir);
    $list=$this->prepareData($data,$dir);                   // clean of '(".", "..")', filtered dirs and exts, sort by name and put directories first
    $pad=$this->getPadding($level);                         // spaces from $this->pad
    
    $count=count($list);
    if(!$count) return false;                               // if no files/subdirs or files excluded by filters don't show this dir
    
    foreach ($list as $value) {
      $item=$dir . DIRECTORY_SEPARATOR . $value;
      
      if (is_dir($item)) {
        $passed=true;
        if($this->filterDir && $level==-1){
          $passed=$this->filterDirectories($value);         // filter directories
        }
        if(!$passed) continue;
        
        $currentDir="[".$value."]";
        $this->markup[]=$pad.$this->wrapDir($currentDir);   // add markup as new line
        $this->text[]=$pad.$currentDir;                     // add text as new line
        
        $res=$this->fullScan($item, $level+1);              
          
        if(!$res){                                          // skip empty/filtered dirs and don't show
          array_pop($this->text);
          array_pop($this->markup);
          $count--;                                         // reduce count of current files/subfolders (if 0 then the current directory is skipped)
        }
        
        if($res){
          $json[]=array(
            "text"=>$this->fixEncoding($value),             // special json formatting for jsTree.js
            "children"=>$res
          );
        }
      }
      else{
        $currentFile=$value;
        $this->markup[]=$pad.$this->wrapFile($currentFile);       // file to markup as new line
        $this->text[]=$pad.$currentFile;
        
        $json[]=array(
          "text"=>$this->fixEncoding($value),                     // json format for jsTree.js
          "icon"=>$this->getIcon($value)
        );
      }
    }
    
    if(!$count) return false;                                     // no items in the current directory after all filterings
    return $json;                                                 // accumulate json array (later will be encoded in a json string)
  }
  
// --------------------------------------------------------- Helpers ---------------------------------------------------------
  
  /*
   * Filters files and folders
   * Sorts by name and directories-first order
   */
  public function prepareData($data,$dir){
    $minus = array(".", "..");
    $data = array_diff($data, $minus);
    
    $folders=[]; $files=[];
    
    foreach ($data as $value) {
      $item=$dir.'/'.$value;
      if (is_dir($item))
        $folders[]=$value;
      else if($this->filterFile($value))
        $files[]=$value;
    }
    
    $list=$this->getList($folders,$files);
    return $list;
  }
  
  /*
   * Merge folders and files
   */
  public function getList($folders,$files){
    $flags=SORT_FLAG_CASE | SORT_STRING;
    sort($folders,$flags); 
    sort($files,$flags);
    
    $list=array_merge($folders,$files);
    return $list;
  }
  
  /*
   * Filters file extensions and returns true if the file will be included in the output
   * If exlude filter is not empty ignores the include filter
   */
  public function filterFile($value){
    if($this->excludeExt){
      foreach($this->excludeExt as $ext){
        $pattern="/\.".$ext."$/";
        if(preg_match($pattern, $value)!==0)
          return false;
      }
      return true;
    }
    
    if(!$this->filterExt) return true;
    foreach($this->filterExt as $ext){
      $pattern="/\.".$ext."$/";
      if(preg_match($pattern, $value)!==0)
        return true;
    }
    return false;
  }
  
  /*
   * Uses form filter to filter directories from the first scanning level
   */
  public function filterDirectories($dir){
    $result=[];
    
    foreach($this->filterDir as $filter) {
      $filter=trim($filter);
      if($filter==$dir)
        return true;
    }
    return false;
  }
  
  /*
   * Cleans, trims and checks filters for emptiness
   */
  public function getFilters($filter){
    $filter=trim($_POST[$filter]);
    
    if($filter){
      $filter=explode("\n",$filter);
      foreach($filter as &$item){
        $item=trim($item);
      }
    }
    return $filter;
  }
  
  /*
   * Gets text for the tree template
   */
  public function getFiltersText(){
    $filterExt="";
    $filterDir="";
    
    if($this->filterExt){
      $filterExt=join(",",$this->filterExt);
    }
    if($this->filterDir){
      $filterDir=join(",",$this->filterDir);
    }
    
    $filters='Files ['.$filterExt.']';
    $filters.=', Directories ['.$filterDir.']';
    
    return $filters;
  }
  
  /*
   * Formats path, fixes backslashes, trims and removes last slash
   */
  public function formatPath(){
    $path=$_POST['path'];
    $path=str_replace("\\", "/", $path);                    // fix '\' backslash in the path
    $path=trim($path);
    if(substr($path,-1)=="/")
      $path=substr($path,0,-1);
    
    // $enc=mb_detect_encoding($path);
    // $path=mb_convert_encoding($path, "utf-8");
    // var_dump($path);
    
    // $text=$path;
    // $res="";
    // foreach(mb_list_encodings() as $enc){
    //   $conv=mb_convert_encoding($text, "UTF-8", $enc);
    //   $res.=$enc.": ".$conv."\n";   
    // }
    // echo "<pre>".$res."</pre>";
    
    
    $this->path=$path;
  }
  
  /*
   * Converts encoding mainly for russian symbols
   */
  public function fixEncoding($text){
    $text=mb_convert_encoding($text, 'utf-8', 'cp1251');
    return $text;
  }
  
  /*
   * Replaces strings from the tree template (strings format: '_string_') with the 'replacement' text
   */
  public function replaceTemplate($tmpl, $replacement, &$text){
    $text=str_replace($tmpl, $replacement, $text);
  }
  
  /*
   * Outputs padding spaces for text output depending on nesting level
   */
  public function getPadding($level){
    $pad=$this->pad;
    $resPad="";
    for($i=0;$i<=$level;$i++)
      $resPad.=$pad;
    return $resPad;
  }
  
  /*
   * Returns icon path for the tree view
   */
  public function getIcon($file){
    $ext="";
    $icon="jstree-file";
    $path=$this->iconsPath;
    $iconExt=".png";
    
    preg_match("/\.[\w]+$/",$file,$ext);
    if(!$ext) return $icon;
    $ext=substr($ext[0],1);
    
    $useDefault=true;
    
    if($useDefault){
      foreach($this->exts as $item){
        if($ext==$item){
          $icon=$path.$item.$iconExt;
          $useDefault=false;
          break;
        }
      }
    }
    
    if($useDefault){
      foreach($this->imageExts as $item){
        if($ext==$item){
          $icon=$path."image".$iconExt;
          $useDefault=false;
          break;
        }
      }
    }
    
    if($useDefault){
      foreach($this->musicExts as $item){
        if($ext==$item){
          $icon=$path."music".$iconExt;
          $useDefault=false;
          break;
        }
      }
    }
    
    if($useDefault){
      foreach($this->videoExts as $item){
        if($ext==$item){
          $icon=$path."video".$iconExt;
          $useDefault=false;
          break;
        }
      }
    }
    
    return $icon;
  }
  
// --------------------------------------------------------- Wrappers ---------------------------------------------------------
  
  /*
   * Joins all the text from the array of lines, encodes it and wraps with <pre>
   */
  public function wrapText(){
    $text=$this->text;
    $text=join("\n",$text);
    $text=mb_convert_encoding($text, 'utf-8', 'cp1251');

    $text=trim($text);
    if(!$text) return false;
    
    $text='<pre>'.nl.$text.nl.'</pre>';
    
    return $text;
  }
  
  /*
   * Joins all items from the markup array (each item is a line wrapped with a <span class="directory/file">), encodes it and wraps with <pre>
   */
  public function wrapMarkup(){
    $markup=$this->markup;
    $markup=join("\n",$markup);
    $markup=mb_convert_encoding($markup, 'utf-8', 'cp1251');
    $markup='<pre>'.nl.$markup.nl.'</pre>';
    $markup=$this->wrapDocument($markup);
    
    return $markup;
  }
  
  /*
   * Adds document elements for the markup export
   */
  public function wrapDocument($markup){
    $markup='<meta charset="utf-8">'.nl.$markup;
    return $markup;
  }
  
  /*
   * Wraps directory line with span for the markup export
   */
  public function wrapDir($dir){
    return '<span class="directory">'.$dir.'</span>';
  }
  
  /*
   * Wraps file line with span for the markup export
   */
  public function wrapFile($file){
    return '<span class="file">'.$file.'</span>';
  }
  
// --------------------------------------------------------- Exports ---------------------------------------------------------
  
  /*
   * Returns the name that will be used to export text, markup and tree views of the directory structure
   */
  public function getExportName($ext=false){
    $useCurrentDir=false;
    $exportName="no-name";
    
    if(isset($_POST['export_name'])){
      $exportName=trim($_POST['export_name']);
      if(!$exportName)
        $useCurrentDir=true;
    }
    else
      $useCurrentDir=true;
    
    if($useCurrentDir){
      preg_match("/\/[^\/]+$/",$this->path,$exportName);
      $exportName=substr($exportName[0],1);
    }
    
    $name=$exportName;
    if($ext)
      $name=$exportName.$ext;
    
    return $name;
  }
  
  /*
   * Exports .json and .html files to the 'export/tree'
   * The .html file can be used directly to view the tree
   * The jsTree plugin must be in the 'tree/lib'
   *
   * The method gets the .html template from 'templates/tree.html', 
   * replaces template strings with the current data and create new .html in the 'exports/tree'
   * Then creates .json in the 'exports/tree/json' which is read by the script in the exported .html page
   */
  public function exportTree(){
    $treeName=$this->getExportName();
    $tmpl='templates/tree.html';
    
    $exportPath="export/tree/";
    $jsonFolder="json/";
    $jsonPath=$exportPath.$jsonFolder;
    
    $exportDoc=$treeName.".html";
    $exportJSON=$treeName.".json";
    
    $file=fopen($tmpl,"r");
    $text = fread($file, filesize($tmpl));
    fclose($file);
    
    $this->replaceTemplate("_jsonPath_", $jsonFolder.$exportJSON, $text);
    $this->replaceTemplate("_Title_", 'Directory: '.$treeName, $text);
    $this->replaceTemplate("_FolderPath_", 'Directory: '.$this->path, $text);
    
    $filters=$this->getFiltersText();
    $this->replaceTemplate("_Filters_", "Filters: ".$filters, $text);
    
    $file=fopen($exportPath.$exportDoc,"w");
    fwrite($file,$text);
    fclose($file);
    
    $file=fopen($jsonPath.$exportJSON, "w");
    fwrite($file,$this->json);
    fclose($file);
  }
  
}

?>
