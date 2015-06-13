<?php
// +--------------------------------------------------------------------------+
// | Media Gallery Plugin - Geeklog                                           |
// +--------------------------------------------------------------------------+
// | ftpmedia.php                                                             |
// |                                                                          |
// | FTP Upload routines                                                      |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2002-2015 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// | Yoshinori Tahara       taharaxp AT gmail DOT com                         |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

if (strpos(strtolower($_SERVER['PHP_SELF']), strtolower(basename(__FILE__))) !== false) {
    die('This file can not be used on its own!');
}

require_once $_CONF['path'] . 'plugins/mediagallery/include/lib-batch.php';

/**
* FTP Import
*
* @param    int     album_id    album_id upload media
* @return   string              HTML
*
*/
function MG_ftpUpload($album_id)
{
    global $_USER, $_CONF, $_MG_CONF, $LANG_MG00, $LANG_MG01, $LANG_MG03;

    $retval = '';

    $album = new mgAlbum($album_id);

    if ($album->access == 3 || ($album->member_uploads==1 && $_USER['uid'] >= 2)) {
        $T = COM_newTemplate(MG_getTemplatePath($album_id));
        $T->set_file('mupload', 'ftpupload.thtml');
        $T->set_var(array(
            'album_id'          => $album_id,
            'start_block'       => COM_startBlock($LANG_MG03['upload_media']),
            'end_block'         => COM_endBlock(),
            'navbar'            => MG_navbar($LANG_MG01['ftp_media'], $album_id),
            's_form_action'     => $_MG_CONF['site_url'] .'/admin.php',
            'lang_upload_help'  => $LANG_MG03['upload_help'],
            'lang_media_ftp'    => $LANG_MG01['upload_media'],
            'lang_directory'    => $LANG_MG01['directory'],
            'lang_recurse'      => $LANG_MG01['recurse'],
            'lang_delete_files' => $LANG_MG01['delete_files'],
            'lang_caption'      => $LANG_MG01['caption'],
            'lang_file'         => $LANG_MG01['file'],
            'lang_description'  => $LANG_MG01['description'],
            'lang_save'         => $LANG_MG01['save'],
            'lang_cancel'       => $LANG_MG01['cancel'],
            'lang_reset'        => $LANG_MG01['reset'],
            'lang_yes'          => $LANG_MG01['yes'],
            'lang_no'           => $LANG_MG01['no'],
            'lang_ftp_help'     => $LANG_MG03['ftp_help'],
            'album_id'          => $album_id,
            'ftp_path'          => $_MG_CONF['ftp_path'],
            'action'            => 'ftp'
        ));
        $retval .= $T->finish($T->parse('output', 'mupload'));
        return $retval;
    } else {
        COM_errorLog("MediaGallery: user attempted to upload to a restricted album.");
        return COM_showMessageText($LANG_MG00['access_denied_msg']);
    }
}

function MG_listDir($dir, $album_id, $purgefiles, $recurse)
{
    global $_CONF, $_TABLES, $_MG_CONF, $LANG_MG01, $LANG_MG02, $destDirCount, $pCount;

    // What we may do is scan for directories first, build that array
    // then scan for files and build that array, I always want the directories to be on the top!
    // array_multisort()

    $x = strlen($_MG_CONF['ftp_path']);
    $x--;
    if ($_MG_CONF['ftp_path'][$x] == '/' || $_MG_CONF['ftp_path'][$x] == '\\') {
        $directory = $_MG_CONF['ftp_path'] . $dir;
    } else {
        $directory = $_MG_CONF['ftp_path'] . '/' . $dir;
    }

    if (!@is_dir($directory)) {
        return COM_showMessageText($LANG_MG02['invalid_directory'] . '<br' . XHTML . '>' . $directory
               . '  [ <a href=\'javascript:history.go(-1)\'>' . $LANG_MG02['go_back'] . '</a> ]');
    }
    if (!$dh = @opendir($directory)) {
        return COM_showMessageText($LANG_MG02['directory_error']
               . '  [ <a href=\'javascript:history.go(-1)\'>' . $LANG_MG02['go_back'] . '</a> ]');
    }

    $directory = trim($directory);
    if ($directory[strlen($directory)-1] != '/') {
        $directory =  $directory . '/';
    }

    /*
     * Currently we have disabled the selection of Root album.
     * This could cause a problem with the 'create the album structure' feature
     * Need to come up with a better way to handle this.
     */
/*
    $album_selectbox  = '';
    if (SEC_hasRights('mediagallery.admin') || ($_MG_CONF['member_albums'] == 1 && $_MG_CONF['member_album_root'] == 0)) {
        $album_selectbox .= '<option value="0">' . $LANG_MG01['root_album'] . '</option>';
    }
    $root_album = new mgAlbum(0);
    $root_album->buildAlbumBox($album_selectbox, $album_id, 3, -1, 'upload');
    $album_selectbox .= '</select>';
*/

//    $album_selectbox  = MG_buildAlbumBox($root_album, $album_id, 3, -1, 'upload');


    $rowcounter = 0;
    $retval = '';

    $T = COM_newTemplate(MG_getTemplatePath($album_id));
    $T->set_file('admin', 'filelist.thtml');
    $T->set_var(array(
        'lang_put_files'  => $LANG_MG01['put_files'],
        'lang_into_album' => $LANG_MG01['into_album'],
    ));

    $destDirCount++;

    $dest = sprintf("d%04d", $destDirCount);

    // build a select box of valid albums for upload
    require_once $_CONF['path'].'plugins/mediagallery/include/classAlbum.php';
    $album_selectbox  = '<select name="' . $dest . '">' . LB;
    $root_album = new mgAlbum(0);
    $root_album->buildAlbumBox($album_selectbox, $album_id, 3, -1, 'upload');
    $album_selectbox .= '</select>' . LB;

    $T->set_block('admin', 'dirRow', 'dRow');

    $pdir = ($dir == '') ? './' : $dir;

    $T->set_var(array(
        'directory'   => $pdir,
        'destination' => $album_selectbox,
        'dirdest'     => $dest,
    ));

    $T->set_block('admin', 'fileRow', 'fRow');

    // calculate parent directory...

    $dirParts = array();
    $dirParts = explode('/', $dir);
    $numDirs  = count($dirParts);
    $dirPath = '';
    if ($numDirs > 1) {
        for ($x=0; $x < $numDirs - 1; $x++) {
            $dirPath .= $dirParts[$x];
            if ($x < $numDirs - 2) {
                $dirPath .= '/';
            }
        }
        $dirlink = '<a href="' . $_MG_CONF['site_url'] . '/admin.php?mode=list&amp;album_id=' . $album_id
                 . '&amp;dir=' . $dirPath . '">Parent directory</a>';

        $T->set_var(array(
            'row_class'     => ($rowcounter % 2) ? '2' : '1',
            'checkbox'      => '',
            'palbum'        => '',
            'pfile'         => '',
            'dirid'         => '',
            'filename'      => $dirlink,
            'fullname'      => '',
            'filesize'      => '',
            'parent_select' => '',
            'color'         => '',
            'type'          => '',
        ));
        $T->parse('fRow', 'fileRow', true);
        $rowcounter++;
    }

    while (($file = readdir($dh)) != false) {
        if ($file == '..' || $file == '.') {
            continue;
        }
        $filetmp = $directory . $file;
        $filename = basename($file);
        $file_extension = strtolower(substr(strrchr($filename, '.'), 1));

        $isadirectory = 0;
        if (is_dir($filetmp)) {
            $isadirectory = 1;
            $type = 'Directory';
            $fullDir = urlencode($dir . '/' . $filename);
            $dirlink = '<a href="' . $_MG_CONF['site_url'] . '/admin.php?album_id=' . $album_id
                     . '&amp;mode=list&amp;dir=' . $fullDir . '">' . $filename . '</a>';
        }

        if ($isadirectory == 0) {
            switch ($file_extension) {
                case 'jpg':
                case 'bmp':
                case 'tif':
                case 'png':
                    $type = 'Image';
                    break;
                case 'avi':
                case 'wmv':
                case 'asf':
                case 'mov':
                    $type = 'Video';
                    break;
                case 'mp3':
                case 'ogg':
                    $type = 'Audio';
                    break;
                default:
                    $type = 'Unknown';
                    break;
            }
        }

        $max_filesize = DB_getItem($_TABLES['mg_albums'], 'max_filesize', 'album_id=' . intval($album_id));
        $toobig = 0;
        if ($max_filesize != 0 && filesize($filetmp) > $max_filesize) {
            $toobig = 1;
        }
        $pCount++;
        $pvalue = sprintf("i%04d", $pCount);

        $T->set_var(array(
            'row_class'     => ($rowcounter % 2) ? '2' : '1',
            'checkbox'      => '<input type="checkbox" name="pic[]" value="' . $pvalue . '"' . XHTML . '>',
            'palbum'        => '<input type="hidden" name="album_lb_id_' . $pvalue . '" value="' . $dest . '"' . XHTML . '>',
            'pfile'         => '<input type="hidden" name="picfile_' . $pvalue . '" value="' . $filetmp . '"' . XHTML . '>',
            'dirid'         => '<input type="hidden" name="dest" value="' . $dest . '"' . XHTML . '>',
            'filename'      => ($isadirectory ? $dirlink : $filename),
            'fullname'      => $filetmp,
            'filesize'      => COM_numberFormat((filesize($filetmp))/1024) . ' kB',
            'parent_select' => '<select name="parentaid">' . LB . $album_selectbox,
            'color'         => ($toobig ? '<span style="font-color:red;">' : '<span style="font-color:black;">'),
            'type'          => $type,
        ));
        $T->parse('fRow', 'fileRow', true);
        $rowcounter++;
    }

    $T->parse('dRow', 'dirRow', true);
    closedir($dh);

    $retval .= $T->finish($T->parse('output', 'admin'));
    return $retval;
}

function MG_ftpProcess($album_id)
{
    global $_TABLES, $_MG_CONF, $LANG_MG01;

    $session_description = $LANG_MG01['ftp_media'];
    $origin = ($album_id == 0) ? '/index.php' : '/album.php?aid=' . $album_id;
    $session_id = MG_beginSession('ftpimport', $_MG_CONF['site_url'] . $origin, $session_description);
    $purgefiles = COM_applyFilter($_POST['purgefiles'], true);

    $count = count($_POST['pic']);
    if ($count < 1) {
        if ($album_id == 0) {
            echo COM_refresh($_MG_CONF['site_url'] . '/index.php');
        } else {
            echo COM_refresh($_MG_CONF['site_url'] . '/album.php?aid=' . $album_id);
        }
        exit;
    }

    foreach ($_POST['pic'] as $pic_id) {
        $album_lb_id = COM_applyFilter($_POST['album_lb_id_' . $pic_id]);
        $aid         = COM_applyFilter($_POST[$album_lb_id], true);
        $filename    = COM_applyFilter($_POST['picfile_' . $pic_id]); // full path and name
        $file        = basename($filename); // basefilename
        $mid         = is_dir($filename) ? 1 : 0;
        MG_registerSession(array(
            'session_id' => $session_id,
            'mid'        => $mid,
            'aid'        => $aid,
            'data'       => $filename,
            'data2'      => $purgefiles,
            'data3'      => $file
        ));
    }

    $display = MG_continueSession($session_id, 0, $_MG_CONF['def_refresh_rate']);
    $display = MG_createHTMLDocument($display);
    echo $display;
    exit;
}

/**
* Displays pick list of files to process...
*
* @param    int     album_id    album_id save uploaded media
* @return   string              HTML
*
*/
function MG_FTPpickFiles($album_id, $dir, $purgefiles, $recurse)
{
    global $_CONF, $_MG_CONF, $LANG_MG01, $LANG_MG03, $destDirCount, $pCount;

    $destDirCount = 0;
    $pCount       = 0;

    $retval = '';

    $T = COM_newTemplate(MG_getTemplatePath($album_id));
    $T->set_file('admin', 'ftpimport.thtml');
    $T->set_var(array(
        'start_block'       => COM_startBlock($LANG_MG03['upload_media']),
        'end_block'         => COM_endBlock(),
        'navbar'            => MG_navbar($LANG_MG01['ftp_media'], $album_id),
        'lang_title'        => $LANG_MG01['title'],
        'lang_description'  => $LANG_MG01['description'],
        'lang_parent_album' => $LANG_MG01['parent_album'],
        'lang_filelist'     => $LANG_MG01['file_list'],
        'lang_quick_create' => $LANG_MG01['quick_create'],
        'lang_checkall'     => $LANG_MG01['check_all'],
        'lang_uncheckall'   => $LANG_MG01['uncheck_all'],
        'dir'               => $dir,
        'purgefiles'        => $purgefiles,
        'recurse'           => $recurse,
        'album_id'          => $album_id,
    ));

    $filelist = MG_listDir($dir, $album_id, $purgefiles, $recurse, $session_id);

    $album_jumpbox  = '<select name="parentaid">';
    if (SEC_hasRights('mediagallery.admin')) {
        $album_jumpbox .= '<option value="0">' . $LANG_MG01['root_album'] . '</option>';
    } else {
        $album_jumpbox .= '<option disabled value="0">' . $LANG_MG01['root_level'] . '</option>';
    }
    $root_album = new mgAlbum(0);
    $root_album->buildJumpBox($album_jumpbox, 0, 3);
    $album_jumpbox .= '</select>';

    $T->set_var(array(
        's_form_action' => $_MG_CONF['site_url'] . '/admin.php',
        'action'        => 'ftpprocess',
        'lang_save'     => $LANG_MG01['save'],
        'lang_cancel'   => $LANG_MG01['cancel'],
        'parent_select' => $album_jumpbox,
        'filelist'      => $filelist
    ));

    $retval .= $T->finish($T->parse('output', 'admin'));

    return $retval;
}
?>