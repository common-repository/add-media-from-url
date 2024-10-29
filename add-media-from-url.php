<?php
/*
Plugin Name: Add Media from URL
Plugin URI: https://www.duy-pham.fr/
Author: LordPretender
Author URI: https://www.duy-pham.fr/
Version: 1.0.2
Description: Let you add media files into your media library without having to upload them.
*/

/**
* Permet de savoir si le média est une image ou non.
* @param int ID de l'attachment.
* 
* @return boolean True / False
*/

function LP_AMFU_isImageFromURL($attachment) {
	$meta = get_post_meta($attachment, LP_AMFU_Main::MEDIATYPE);
	
	return count($meta) > 0;
}

class LP_AMFU_Main {

	const LIB_TITRE		= "Add Media from URL";
	const SAI_ADD 		= "Add from URL";
	const MEDIATYPE		= "AddedFromURL-";
	const COMBO_GDRIVE	= "Google Drive";
	const COMBO_OTHER	= "Other";
	
	public function __construct() {

		//Ajout d'un élément dans le menu de l'APC
		add_action('admin_menu', array($this,"plugin_menu"));
		
		//Surchage permettant d'utiliser le lien de l'image source
		add_filter( 'wp_get_attachment_image_src', array($this,"getAttachmentImageSrc"), 10, 4 );
		
		//Surcharge permettant d'afficher les dimensions
		add_filter( 'wp_get_attachment_metadata', array($this,"getAttachmentMetadata"), 10, 2 );
		
		//Surcharge permettant d'afficher correctement le nom du fichier (ajout de l'extension).
		add_filter( 'get_attached_file', array($this,"getAttachedFile"), 10, 2 );
		
		//Surcharge afin d'ajouter une liste déroulante de la bibliothèque de médias
		add_action( 'restrict_manage_posts', array($this,"RestrictManagePosts") );
		
		//Surcharge permettant de prendre en charge la nouvelle liste déroulante
		add_filter( 'parse_query', array($this,"RestrictManagePostsValidation") );
		
	}
	
	/**
	* Construction de la nouvelle liste déroulante permettant d'afficher les fichiers qui n'ont pas été upload.
	*/
	public function RestrictManagePosts() {
		
		$choix = isset($_GET[self::MEDIATYPE . 'filter']) ? intval($_GET[self::MEDIATYPE . 'filter']) : 0;
		
		$choix0 = $choix == 0 ? ' selected="selected"' : '';
		$choix1 = $choix == 1 ? ' selected="selected"' : '';
		$choix2 = $choix == 2 ? ' selected="selected"' : '';
	?>
	<label for="<?php echo self::MEDIATYPE . 'filter'; ?>" class="screen-reader-text">Filter by uploading type</label>
	<select name="<?php echo self::MEDIATYPE . 'filter'; ?>" id="<?php echo self::MEDIATYPE . 'filter'; ?>">
		<option<?php echo $choix0; ?> value="0">Uploaded and not uploaded files</option>
		<option<?php echo $choix1; ?> value="1">Uploaded files only</option>
		<option<?php echo $choix2; ?> value="2">Not uploaded files only</option>
	</select>
	<?php

	}
	
	public function RestrictManagePostsValidation($query) {
		
		global $pagenow;
		
		if( is_admin() ) {
			
			//Bibliothèque de médias
			if ( $pagenow == 'upload.php' ) {
				
				$choix = isset($_GET[self::MEDIATYPE . 'filter']) ? intval($_GET[self::MEDIATYPE . 'filter']) : 0;
				
				if( $choix > 0 ) {
					
					$compare = $choix == 2 ? 'EXISTS' : 'NOT EXISTS';
					
					$query->query_vars['meta_query'] = array(
					    array(
					     'key' => self::MEDIATYPE,
					     'compare' => $compare
					    ),
					);
					
				}
				
			}
			
		}

	}
	
	/**
	* Cette méthode sert à modifier le menu du backend afin d'y ajouter un lien vers notre page principale.
	*/
	public function plugin_menu() {
		add_media_page(self::LIB_TITRE, self::SAI_ADD, 'read', str_replace(__dir__ . "/", '', __file__), array($this,"uploadPage"));
	}
	
	/**
	* Cette méthode charge la page principale du plugin.
	*/
	public function uploadPage() {

		//Contrôle d'accès
		if (!current_user_can('upload_files')) wp_die( __('You do not have sufficient permissions to access this page.') );

		//Contenu du formulaire
		if (sanitize_text_field($_POST[ 'AddMediaFromURL_Name' ])) $valueName = sanitize_text_field($_POST['AddMediaFromURL_Name']);
		if (sanitize_text_field($_POST[ 'AddMediaFromURL_Url' ])) $valueUrl = sanitize_text_field($_POST['AddMediaFromURL_Url']);
		if (sanitize_text_field($_POST[ 'AddMediaFromURL_Upload' ] === "1")) $valueUpload = ' checked';
		
		$service0 = sanitize_text_field($_POST['AddMediaFromURL_Type']) == self::COMBO_OTHER || !isset($_POST['AddMediaFromURL_Type']) ? ' selected="selected"' : '';
		$service1 = sanitize_text_field($_POST['AddMediaFromURL_Type']) == self::COMBO_GDRIVE ? ' selected="selected"' : '';
		
		//Validation du formulaire
		if( isset($_POST['AddMediaFromURL_Nonce']) ) {
			
			$return = $this->handle_upload();

			if ( is_wp_error( $return ) ) {

				//Upload has failed add to a global for display on the form page
		        foreach ( $return->get_error_messages() as $error ) {

		            echo '<div class="error"><p><strong>' . $error . '</strong></p></div>';

				}

			} else {

				//Upload has succeeded, redirect to mediapage
				wp_safe_redirect( admin_url( 'post.php?post='.$return.'&action=edit') );
				exit();

			}
			
		}

		?>
<div class="wrap">
	
	<h2><?php echo self::LIB_TITRE; ?></h2>
	
	<form method="post">
		<input type="hidden" value="<?php echo wp_create_nonce("AddMediaFromURL_Url"); ?>" name="AddMediaFromURL_Nonce" id="AddMediaFromURL_Nonce">
		
		<table class="form-table"><tbody>
		
			<tr>
				<th scope="row"><label for="AddMediaFromURL_Name">Title</label></th>
				<td>
					<input name="AddMediaFromURL_Name" type="text" id="AddMediaFromURL_Name" value="<?php echo $valueName; ?>" class="regular-text ltr">
					<p class="description">This is not the file name, just the title of the media entry.</p>
				</td>
			</tr>
		
			<tr>
				<th scope="row"><label for="AddMediaFromURL_Type">Type</label></th>
				<td>
					<select name="AddMediaFromURL_Type" id="AddMediaFromURL_Type">
						<option<?php echo $service1; ?> value="<?php echo self::COMBO_GDRIVE; ?>"><?php echo self::COMBO_GDRIVE; ?></option>
						<option<?php echo $service0; ?> value="<?php echo self::COMBO_OTHER; ?>"><?php echo self::COMBO_OTHER; ?></option>
					</select>
					
					<p class="description">
						The third-party service (Dropbox, Box, OneDrive, Google Drive, Instagram, ...) where the file is located. 
						Selecting the correct service will let you enter only the ID of the file (instead of the full link).
					</p>
				</td>
			</tr>
		
			<tr>
				<th scope="row"><label for="AddMediaFromURL_Url">Remote link</label></th>
				<td>
					<input name="AddMediaFromURL_Url" type="text" id="AddMediaFromURL_Url" value="<?php echo $valueUrl; ?>" class="regular-text ltr">
					
					<p class="description">Full link if "<?php echo self::COMBO_OTHER; ?>" is selected otherwise, only the file ID (based on the service).</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="AddMediaFromURL_Upload">Upload file</label></th>
				<td>
					<input name="AddMediaFromURL_Upload" type="checkbox" id="AddMediaFromURL_Upload" value="1" class="regular-text ltr"<?php echo $valueUpload; ?>>
					
					<p class="description">If checked, the file will be uploaded to your Wordpress. Usefull if you do not want to have to save file in local first.</p>
				</td>
			</tr>
			
		</tbody></table>
		
		<h2 class="title">For your information...</h2>
		<p>
			This function is available : LP_AMFU_isImageFromURL ( [attachment id] ). <br />Returns TRUE if this image has been added into the library without upload.
			<br />In some case, you will have to use the guid of the attachment that's why this function exists.
		</p>
		
		<?php submit_button(); ?>
	</form>
</div>
		<?php

	}
	
	/**
	* Le formulaire a été validé, on récupère les informations afin de créer le média (avec upload si demandé).
	*/
	private function handle_upload(){

		if (current_user_can('upload_files')){

			if (!wp_verify_nonce( sanitize_text_field($_POST['AddMediaFromURL_Nonce']), "AddMediaFromURL_Url")) return new WP_Error('LP_AMFU_Main', 'Could not verify request nonce');
			
			$type = sanitize_text_field($_POST['AddMediaFromURL_Type']);
			$link = ($type == self::COMBO_GDRIVE ? 'https://docs.google.com/uc?id=' : '') . sanitize_text_field($_POST['AddMediaFromURL_Url']);
			
			$upload_name = sanitize_text_field($_POST['AddMediaFromURL_Name']);
			$upload_url = esc_url($link);
			$upload_ddl = sanitize_text_field($_POST['AddMediaFromURL_Upload']) === "1" ? TRUE : FALSE;
			
			//Pas de titre..
			if( $upload_name == '' ) return new WP_Error('LP_AMFU_Main', 'You have to enter a title');
			
			//Lien non valide...
			if ( filter_var($upload_url, FILTER_VALIDATE_URL) === false ) return new WP_Error('LP_AMFU_Main', 'The link is not valid : ' . $upload_url);
			
			// build up array like PHP file upload
			$file = array();
			$file['name'] = $upload_name;
			$file['tmp_name'] = $upload_ddl ? download_url($upload_url) : $upload_url;
			
			if (is_wp_error($file['tmp_name'])) {
				@unlink($file['tmp_name']);
				return new WP_Error('LP_AMFU_Main', 'Could not download file from remote source');
			}
			
			if (!is_wp_error($file['tmp_name'])) {
					
				if($upload_ddl) {
					
					$attachmentId = media_handle_sideload($file, "0");
					
					// create the thumbnails
					$attach_data = wp_generate_attachment_metadata( $attachmentId, get_attached_file($attachmentId));
					
					wp_update_attachment_metadata( $attachmentId,  $attach_data );
					
				} else {
					
					$infos = getimagesize($upload_url);
					$width = $infos[0];
					$height = $infos[1];
					$mime = $infos['mime'];
					
					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid'           => $upload_url, 
						'post_mime_type' => 'import',
						'post_title'     => $upload_name,
						'post_content'   => '',
						'post_status'    => 'inherit'
					);
					
					$attachmentId = wp_insert_attachment( $attachment );
										
					update_post_meta($attachmentId, self::MEDIATYPE, "1");
					update_post_meta($attachmentId, self::MEDIATYPE . "width", $width);
					update_post_meta($attachmentId, self::MEDIATYPE . "height", $height);
					update_post_meta($attachmentId, self::MEDIATYPE . "mime", $mime);
					
				}

				return $attachmentId;	

			}

		}

	}
	
	/**
	* Retrieve the attachment object from the cache is already requested.
	* 
	* @param int $ID ID of the attachment
	* 
	* @return Object.
	*/
	private function get_attachment($ID) {
		$key = LP_AMFU_Main::MEDIATYPE . $ID;
		
		//https://codex.wordpress.org/Class_Reference/WP_Object_Cache
		$attachment = wp_cache_get( $key );
		if ( false === $attachment ) {
			$attachment = get_post($ID);
			wp_cache_set( $key, $attachment );
		}
		
		return $attachment;
	}

	/**
	* Modification du lien vers le fichier afin d'y ajouter son extension. Indispensable car il arrive que WP tente de lire l'extension par ce biais.
	*/
	public function getAttachedFile($file, $attachment_id) {
		
		$attachment = $this->get_attachment($attachment_id);
		if( LP_AMFU_isImageFromURL($attachment_id) ) {
			
			$mime = get_post_meta($attachment_id, LP_AMFU_Main::MEDIATYPE . "mime", TRUE);
			
			$file = $attachment->guid . str_replace("/", ".", $mime);
			
		}
		
		return $file;
	}
	
	/**
	* Cette surcharge permet d'afficher les dimensions de l'image dans la page du média.
	*/
	public function getAttachmentMetadata($media_dims, $post) {
		
		$attachment = $this->get_attachment($post);
		
		if( LP_AMFU_isImageFromURL($post) ) {
			
			$width = get_post_meta($post, LP_AMFU_Main::MEDIATYPE . "width", TRUE);
			$height = get_post_meta($post, LP_AMFU_Main::MEDIATYPE . "height", TRUE);
			
			$media_dims = array('width' => $width, 'height' => $height);
			
		}
				
		return $media_dims;
	}
	
	/**
	* Sert à forcer l'utilisation du lien pour un affichage correct des images.
	*/
	public function getAttachmentImageSrc($attr, $attachment_id, $size, $icon) {
		
		$attachment = $this->get_attachment($attachment_id);
		
		if( LP_AMFU_isImageFromURL($attachment_id) ) {
			$attr[0] = $attachment->guid;
		}

		return $attr;
	}
	
}

new LP_AMFU_Main();

?>