<?php
/*
Plugin Name: افزونه Paid Downalod ایرپول
Plugin URI: https://irpul.ir/plugins
Description: توسط این افزونه می توانید امکان دانلود فایل پس از پرداخت وجه را در سایت خود قرار دهید.
Version: 1.4
Author: irpul.ir
Author URI: https://irpul.ir
License: GPL2
*/
DPFileDownload::init();

class DPFileDownload {
	protected static $currencies = array(
		'USD' => array('United States Dollar','$'),
		'AUD' => array('Australian Dollar','AUD$'),
		'BRL' => array('Brazilian Real','R$'),
		'GBP' => array('British Pound','&pound;'),
		'CAD' => array('Canadian Dollar','CAD$'),
		'CNY' => array('Chinese Yuan','&#20803;'),
		'DKK' => array('Danish Krone','kr.'),
		'EUR' => array('European Euro','&#8364;'),
		'HKD' => array('Hong Kong Dollar','HK$'),
		'HUF' => array('Hungarian Forint','Ft'),
		'INR' => array('Indian Rupee','INR'),
		'IDR' => array('Indonesian Rupiah','Rp'),
		'JPY' => array('Japanese Yen','&yen;'),
		'MXN' => array('Mexican Peso','MEX$'),
		'NZD' => array('New Zealand Dollar','NZ$'),
		'NOK' => array('Norwegian Kroner','kr'),
		'PLN' => array('Polish Zloty','zl.'),
		'RUB' => array('Russian Ruble','RUB'),
		'SAR' => array('Saudi Riyal','SR'),
		'SGD' => array('Singapore Dollar','SGD$'),
		'ZAR' => array('South African Rand','R'),
		'SEK' => array('Swedish Krona','kr'),
		'CHF' => array('Swiss Franc','CHF'),
		'THB' => array('Thai Bhat','&#3647;'),
		'TRY' => array('Turkish Lira','TRY'),
		'TWD' => array('Taiwan Dollar','TWD')
	);

	const VERSION = '1.3';
	const DB_VERSION = "1.0";

	public static function init() {
		register_activation_hook(__FILE__, array(__CLASS__, 'install'));

		// admin stuff
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_init', array(__CLASS__, 'admin_init'));

		// media buttons hook
		add_action('media_buttons_context', array(__CLASS__, 'media_button'));

		// insert form
		add_action('admin_footer', array(__CLASS__, 'add_pd_pfd_form'));

		// listener for ipn activation
		add_action('template_redirect', array(__CLASS__, 'var_listener'));
		add_filter('query_vars', array(__CLASS__, 'register_vars'));

		add_action('admin_menu', array(__CLASS__, 'add_meta_box'));
	}

	protected static function transactioncode($length = "") {
		$code = md5(uniqid(rand(), true));
		if ($length != "") return strtoupper(substr($code, 0, $length));
		else return strtoupper($code);
	}

	protected static function relative_time($ptime) {
		$etime = time() - $ptime;

		if ($etime < 1) {
			return '0 seconds';
		}

		$a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
					30 * 24 * 60 * 60       =>  'month',
					24 * 60 * 60            =>  'day',
					60 * 60                 =>  'hour',
					60                      =>  'minute',
					1                       =>  'second'
					);

		foreach ($a as $secs => $str) {
			$d = $etime / $secs;
			if ($d >= 1) {
				$r = round($d);
				return $r . ' ' . $str . ($r > 1 ? 's' : '');
			}
		}
	}

	public static function add_meta_box() {
		add_meta_box( 'pd_pfd_sectionid', "افزونه Paid Downalod ایرپول", array(__CLASS__, 'meta_box'), 'post', 'side', 'high' );
		add_meta_box( 'pd_pfd_sectionid', "افزونه Paid Downalod ایرپول", array(__CLASS__, 'meta_box'), 'page', 'side', 'high' );
	}
	
	public static function meta_box() {
//		echo '<div style="margin-top:5px;margin-bottom:5px;">';
		echo '<a href="#TB_inline?width=450&inlineId=dp_paypal_file_download_form" title="قرار دادن لینک پرداخت" class="thickbox button" >قرار دادن لینک پرداخت</a>';
//		echo '</div>';
	}

	public static function install() {
		global $wpdb;

		$message_default = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد<br/>شماره سفارش [ORDER_ID].
EOT;

		$message_default_nofile = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد<br/>شماره سفارش [ORDER_ID].
EOT;

		add_option("pd_email_message", $message_default, '','yes');
		add_option("pd_email_message_nofile", $message_default_nofile, '','yes');
		add_option("pd_expire_links_after", 7, '','yes');
		add_option("pd_token", "", '','yes');
		add_option("pd_paypal_direct", 0 , '','yes');
		add_option("pd_paypal_return_url", get_option("siteurl"), '','yes');
		add_option("pd_get_hamrah", 0 , '','yes');

		$table_name = $wpdb->prefix . "pd_pfd_products";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				file VARCHAR(255) NOT NULL,
				downloads bigint(11) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		$table_name = $wpdb->prefix . "pd_pfd_orders";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				fulfilled mediumint(9) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}


		$table_name = $wpdb->prefix . "pd_pfd_transactions";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				protection_eligibility VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				tax bigint(11) NULL,
				payment_date VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				first_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				last_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				business VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_street VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_city VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_state VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_zip VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				quantity VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				verify_sign VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				txn_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
				txn_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				mc_currency VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_number VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				residence_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				custom VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receipt_id VARCHAR(255)  NULL,
				transaction_subject VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_fee bigint(11) NOT NULL,
				payment_gross bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		add_option("pd_pfd_db_version", self::DB_VERSION);
	}

	protected static function get_currency() {
		if (get_option('pd_pd_pfd_currency')) {
			$cc = get_option('pd_pd_pfd_currency');
		} else {
			$cc = "USD";
		}

		return $cc;
	}

	protected static function get_currency_symbol() {
		$cc = self::get_currency();

		return self::$currencies[$cc][1];
	}

	public static function validate_currency($currency) {
		if (!empty(self::$currencies[$currency]))
			return $currency;
		return 'USD';
	}

	public static function admin_init() {
		register_setting('pd_pd_pfd_options', 'pd_email_message');
		register_setting('pd_pd_pfd_options', 'pd_email_message_nofile');
		register_setting('pd_pd_pfd_options', 'pd_expire_links_after', 'intval');
		register_setting('pd_pd_pfd_options', 'pd_token');
		register_setting('pd_pd_pfd_options', 'pd_paypal_direct');
		register_setting('pd_pd_pfd_options', 'pd_paypal_return_url');
		register_setting('pd_pd_pfd_options', 'pd_get_hamrah');
		register_setting('pd_pd_pfd_options', 'pd_pd_pfd_currency', array(__CLASS__, 'validate_currency'));
	}

	public static function admin_menu() {
		add_menu_page( "افزونه Paid Downalod ایرپول", "افزونه Paid Downalod ایرپول", 'manage_options', 'dp-file-download', array(__CLASS__, 'admin_dashboard'));
		add_submenu_page( 'dp-file-download', "افزونه Paid Downalod ایرپول - محصولات", "محصولات", 'manage_options', "dp-file-download-products", array(__CLASS__, 'admin_products_router'));
		add_submenu_page( 'dp-file-download', "افزونه Paid Downalod ایرپول - تراکنش ها", "تراکنش ها", 'manage_options', "dp-file-download-transactions", array(__CLASS__,'admin_transactions'));
		add_submenu_page( 'dp-file-download', "افزونه Paid Downalod ایرپول - تنظیمات", "تنظيمات", 'manage_options', "dp-file-download-settings", array(__CLASS__, 'admin_settings'));
		global $submenu;
		$submenu['dp-file-download'][0][0] = 'صفحه اصلی';
	}

	public static function admin_products_router() {
		$action = '';
		if (!empty($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
		}

		switch ($action) {
			case 'edit':
				return self::admin_products_edit();
				break;
			case 'delete':
				return self::admin_products_delete();
				break;
			case 'add':
				return self::admin_products_add();
				break;
			default:
				return self::admin_products();
		}
	}

	protected static function admin_products_edit() {

		global $wpdb;
		$table_name = $wpdb->prefix . "pd_pfd_products";
		
		if (isset($_POST["product_name"])) {

			$name = $_POST["product_name"];
			$url = $_POST["product_url"];
			$secondUrl = $_POST["product_second_url"];
			
			$url = $url . ' | ' . $secondUrl;
			
			$cost = $_POST["product_cost"];

			$wpdb->update( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost), array('id' => $_GET["id"]), array( '%s', '%s', '%s'));
		}
		
		$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$_GET["id"]) , ARRAY_A, 0);
		$exp = explode(" | ", $product['file']);
		$product['file'] = $exp[0];
		$product['secondLink'] = $exp[1];
	?>
	<div class="wrap">
		<h2>ويرايش محصول: <?php echo $product['name'] ?></h2>
		<a href="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=dp-file-download-products">&laquo; بازگشت به صفحه محصولات</a>
		<form action="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=dp-file-download-products&action=edit&id=<?php echo $_GET['id'] ?>" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="<?php echo str_replace('"','\"',$product["name"]); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url"  dir="ltr"  style="width:500px;" value="<?php echo str_replace('"','\"',$product["file"]); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لینک دوم محصول</th>
				<td><input type="text" name="product_second_url" dir="ltr" style="width:500px;" value="<?php echo str_replace('"','\"',$product["secondLink"]); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">توضیحات</th>
				<td>
					- از مخفی بودن لینک های پرداخت اطمینان حاصل کنید.<br />
					- لينک های پرداخت پس از خريد موفق به خريدار نشان داده مي شود.<br/>
					- لینک های دانلود باید به یک فایل اشاره کنند.
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">قیمت محصول</th>
				<td><input type="text" name="product_cost" dir="ltr" style="width:90px;" value="<?php echo str_replace('"','\"',$product["cost"]); ?>" />ریال</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="ذخيره کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>
	<?php
	}

	protected static function admin_products_delete() {
		// delete and redirect
		global $wpdb;
		$table_name = $wpdb->prefix . "pd_pfd_products";

		$id = $_GET["id"];

		$wpdb->query("DELETE FROM $table_name WHERE id = '$id'");
		?>
		<script type="text/javascript">
		<!--
		window.location = "<?php echo get_option('siteurl') . '/wp-admin/admin.php?page=dp-file-download-products' ?>"
		//-->
		</script>
		<?php
	}

    public static function admin_dashboard() {
	?>
    <div class="wrap" style="font-size:16px">
		<br /><br /><br />
		<ul>
			<li><a href="?page=dp-file-download-products">محصولات</a></li>
			<li><a href="?page=dp-file-download-transactions">تراکنش ها</a></li>
			<li><a href="?page=dp-file-download-settings">تنظیمات</a></li>
		</ul>

		<br /><br /><br />
		<div align="left" style="font-size:15px">
			<a href="https://irpul.ir" target="_blank" >https://irpul.ir</a>
		</div>
	</div>
	<?php
    }

	protected static function admin_products() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>محصولات</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="5%" class="manage-column" style="">رديف</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" id="name" width="5%" class="manage-column" style="">رديف</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</tfoot>

			<tbody>
				<?php
				global $wpdb;
				$table_name = $wpdb->prefix . "pd_pfd_products";
				$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC" ,ARRAY_A);
				if (count($products) == 0) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="" colspan="5">هيچ محصولي موجود نيست</td>
				</tr>
				<?php
				} else {
				foreach ($products as $product) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class=""><?php echo $product['id'] ?></td>
					<td class="post-title column-title"><strong><a class="row-title" href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=dp-file-download-products&action=edit&id=<?php echo $product['id'] ?>"><?php echo $product['name'] ?></a></strong></td>
					<td class=""><?php echo $product['cost'] ?> ریال</td>
					<td class="" style="text-align:center;"><?php echo $product['downloads'] ?></td>
					<td class="" style="text-align:center;"><a href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=dp-file-download-products&action=edit&id=<?php echo $product['id'] ?>">ويرايش</a></td>
					<td class="" style="text-align:center;"><a href="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=dp-file-download-products&action=delete&id=<?php echo $product['id'] ?>" onclick="if(confirm('آيا از حذف اين مورد اطمينان داريد؟ !')) { return true;} else { return false;}">حذف</a></td>
				</tr>
				<?php } } ?>
			</tbody>
		</table>

		<h2>اضافه نمودن محصول</h2>
		<form action="<?php echo get_option('siteurl') ?>/wp-admin/admin.php?page=dp-file-download-products&action=add" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url" dir="ltr" style="width:500px;" value="" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لینک دوم محصول</th>
				<td><input type="text" name="product_second_url" dir="ltr" style="width:500px;" value="" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">توضیحات</th>
				<td>
					- از مخفی بودن لینک های پرداخت اطمینان حاصل کنید.<br />
					- لينک های پرداخت پس از خريد موفق به خريدار نشان داده مي شود.<br/>
					- لینک های دانلود باید به یک فایل اشاره کنند.
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">قيمت محصول(به ازاي هر بار دانلود)</th>
				<td><input type="text" name="product_cost" dir="ltr" style="width:90px;" value="" />ریال</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="اضافه کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>
	<?php
	}

	protected static function admin_products_add() {
		// get shit
		$name = $_POST["product_name"];
		$url = $_POST["product_url"];
		$secondUrl = $_POST["product_second_url"];
		
		$url = $url . ' | ' . $secondUrl;
		
		$cost = $_POST["product_cost"];

		global $wpdb;
		$table_name = $wpdb->prefix . "pd_pfd_products";

		$wpdb->insert( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost, 'downloads' => 0, 'created_at' => time()), array( '%s', '%s', '%s', '%d', '%d') );

		?>
		<script type="text/javascript">
		<!--
		window.location = "<?php echo get_option('siteurl') . '/wp-admin/admin.php?page=dp-file-download-products' ?>"
		//-->
		</script>
		<?php
	}

	public static function admin_settings() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>تنظيمات درگاه</h2>
<?php
		if (isset($_GET['settings-updated'])) {
			echo '<div id="message" class="updated"><p>تنظيمات به روز شد!</p></div>';
		}
?>
		<form method="post" action="options.php">
			<?php settings_fields('pd_pd_pfd_options'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">توکن درگاه</th>
					<td><input type="text" name="pd_token" style="width:300px;" value="<?php echo get_option('pd_token'); ?>" /><br />پس از ثبت درگاه در سایت <a href="http://irpul.ir" target="_blank">ایرپول</a> ، شناسه درگاه خود را در این فیلد وارد نمایید.</td>
				</tr>
				<tr valign="top">
					<th scope="row">تاريخ انقضاي لينک بعد از...</th>
					<td><input type="text" name="pd_expire_links_after" style="width:50px;" value="<?php echo get_option('pd_expire_links_after'); ?>" /> روز (0 براي بي نهايت)<br />فعال کردن اين قسمت باعث مي شود لينک هاي شما پس از مدت تعيين شده غير فعال شوند</td>
				</tr>
                <tr valign="top">
					<th scope="row">مستقیم کردن لینک دانلود</th>
					<td><input type="checkbox" name="pd_paypal_direct" id="pd_paypal_direct" <?php if(get_option('pd_paypal_direct')=='on') echo 'checked'; ?>  /> <label for="pd_paypal_direct">برای نمایش مستقیم لینک هایی دانلود محصول پس از پرداخت این گزینه را تیک بزنید.</label></td>
				</tr>
				<tr valign="top">
					<th scope="row">دریافت موبایل خریدار</th> 
					<td>
						<input type="checkbox" name="pd_get_hamrah" id="pd_get_hamrah" <?php if(get_option('pd_get_hamrah')=='on') echo 'checked'; ?> /><label for="pd_get_hamrah"> در صورتی که مایل هستید پیش از انتقال به دروازه پرداخت، شماره تلفن خریدار را دریافت کنید این گزینه را تیک بزنید.</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">آدرس بازگشتي</th> 
					<td><input type="text" dir="ltr" name="pd_paypal_return_url" style="width:350px;" value="<?php echo get_option('pd_paypal_return_url'); ?>" /><br />لینک بازگشت به سایت شما بعد از پرداخت</td>
				</tr>
				<tr valign="top">
					<th scope="row">اطلاع رساني</th>
					<td>
						پس از خريد موفق متن زیر براي خريدار به نمايش در خواهد آمد:<br/>
						<textarea name="pd_email_message" style="width:600px;height:150px;"><?php echo get_option('pd_email_message'); ?></textarea><br />
						شما مي توانيد از متغير هاي زير نیز استفاده کنيد: <br />
						[DOWNLOAD_LINK] [PRODUCT_NAME] [TRANSACTION_ID] [ORDER_ID]<br /><br />
						<strong>توجه :</strong> لينک دانلود بصورت اتوماتيک در انتهاي اين متن قرار مي گيرد	<br />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					</td>
				</tr>
			</table>
		</form>
	</div>
	<?php
	}

	public static function admin_transactions() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	?>
	<div class="wrap">
		<h2>تراکنش ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="30%" class="manage-column" style=""> محصول</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style=""> شماره تراکنش</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">تلفن همراه</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" id="name" width="40%" class="manage-column" style="">شماره تراکنش, محصول</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">تلفن همراه</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</tfoot>

			<tbody>
				<?php
				global $wpdb;
				$table_name = $wpdb->prefix . "pd_pfd_transactions";
				$products_name = $wpdb->prefix . "pd_pfd_products";
				$orders_name = $wpdb->prefix . "pd_pfd_orders";

				$query = "SELECT $table_name.order_code, $products_name.name, $table_name.first_name, $table_name.created_at, $table_name.last_name, $table_name.address_street, $table_name.address_city, $table_name.address_state, $table_name.address_zip, $table_name.address_country, $table_name.payment_fee, $table_name.payer_email, $orders_name.cost FROM $table_name JOIN $products_name ON $table_name.product_id = $products_name.id JOIN $orders_name ON $table_name.order_id = $orders_name.id ORDER BY $table_name.id DESC";
				$transactions = $wpdb->get_results( $query ,ARRAY_A);
				
				/* output of $query
				"SELECT wp_pd_pfd_transactions.order_code, wp_pd_pfd_products.name, wp_pd_pfd_transactions.first_name, wp_pd_pfd_transactions.created_at, wp_pd_pfd_transactions.last_name, wp_pd_pfd_transactions.address_street, wp_pd_pfd_transactions.address_city, wp_pd_pfd_transactions.address_state, wp_pd_pfd_transactions.address_zip, wp_pd_pfd_transactions.address_country, wp_pd_pfd_transactions.payment_fee, wp_pd_pfd_transactions.payer_email, wp_pd_pfd_orders.cost FROM wp_pd_pfd_transactions JOIN wp_pd_pfd_products ON wp_pd_pfd_transactions.product_id = wp_pd_pfd_products.id JOIN wp_pd_pfd_orders ON wp_pd_pfd_transactions.order_id = wp_pd_pfd_orders.id ORDER BY wp_pd_pfd_transactions.id DESC"
				*/
				
				if (count($transactions) == 0) {
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="" colspan="7">هيج تراکنش وجود ندارد.</td>
				</tr>
				<?php
				} else {
				foreach ($transactions as $transaction) {
					$exploded = explode(" | ", $transaction['payer_email']);
					$transaction['payer_email'] = wp_specialchars($exploded[0]);
					$transaction['payer_mobile'] = wp_specialchars($exploded[1]);
				?>
				<tr class='alternate author-self status-publish iedit' valign="top">
					<td class="post-title column-title"><strong><?php echo $transaction['name'] ?></strong></td>
                                        <td class="post-title column-title"><strong><?php echo $transaction['order_code'] ?></strong></td>
					<td class=""><?php echo strftime("%a, %B %e, %Y %r", $transaction['created_at']) ?><br />(<?php echo self::relative_time($transaction["created_at"]) ?> ago)</td>
                    <td class=""><?php echo $transaction['payer_email'] ?></td>
                    <td class=""><?php echo $transaction['payer_mobile'] ?></td>
					<td class=""><?php echo $transaction['cost'] ?> ریال</td>
				</tr>

				<?php } } ?>
			</tbody>
		</table>
	</div>
	<?php
	}

	public static function media_button($context){
		$image_url = "http://example.com/wd/images/favicon.png";
		$more = '<a href="#TB_inline?width=450&inlineId=dp_paypal_file_download_form" class="thickbox" title="قرار دادن لینک پرداخت "><img src="' . $image_url . '" alt="قرار دادن لینک پرداخت " /></a>';
		return $context . $more;
	}

	public static function add_pd_pfd_form() {
	?>
	<script type="text/javascript">
		function insert_pd_pfd_button(){
			product_id = jQuery("#dp_product_selector").val()

			dp_image = jQuery("#dp_button_image_url").val()


			//construct = '<a href="<?php echo get_option('siteurl') ?>/?dp_checkout=' + product_id + '"><img src="' + image + '" /></a>';
            construct = '<form name="frm_dp' + product_id + '" action="<?php echo get_option('siteurl') ?>/?dp_checkout=' + product_id + '" method="post"><input type="image" name="submit" src="' + dp_image + '" value="1"></form>';
           alert(construct);

			var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}

		function insert_pd_pfd_link(){
			product_id = jQuery("#dp_product_selector").val()
			construct = '<a href="<?php echo get_option('siteurl') ?>/?dp_checkout=' + product_id + '"><?php echo get_option('siteurl') ?>/?checkout=' + product_id + '</a>';
			alert(construct)
			var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}
	</script>

	<div id="dp_paypal_file_download_form" style="display:none;">
		<div class="wrap">
			<div>
				<div style="padding:15px 15px 0 15px;">
					<h3 style="font-size:16pt !important;line-height:1em !important;color:#555555 !important;">قرارد دادن لينک پرداخت irpul</h3>
					<span>لطفا محصول مورد نظرتان را از لينک زير انتخاب نماييد</span>
				</div>
				<div style="padding:15px 15px 0 15px;">
					<table width="100%">
						<tr>
							<td width="150"><strong>محصول</strong></td>
							<td>
								<select id="dp_product_selector" onchange="pay_code('<?php echo get_option('siteurl') ?>')">
									<?php
									global $wpdb;
									$table_name = $wpdb->prefix . "pd_pfd_products";
									$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC;" ,ARRAY_A);
									if (count($products) == 0) {
									?>
									محصولي وجود ندارد. <a href="<?php echo get_option('siteurl') . "/wp-admin/admin.php?page=paypal-file-download-products" ?>">نوشته خود را ذخيره کنيد و سپس اينجا کليک نماييد.</a>
									<?php
									}
									else {
										echo "<option value='' >لطفا محصول مورد نظر را انتخاب نمائید</option>";
										foreach($products as $product) {
											$pr_id 		= $product["id"];
											$pr_name 	= $product["name"];
											$pr_cost 	= $product["cost"];
											echo "<option value='$pr_id' >$pr_name ($pr_cost ریال)</option>";
										}
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td width="135"><strong>لينک تصوير پرداخت:</strong></td>
							<td><input type="text" id="dp_button_image_url" value="https://irpul.ir/img/buttons/1.png" style="width:220px;" /></td>
						</tr>
						<tr>
							<td width="135"><strong>کد خرید</strong></td>
							<td><input type="text" id="payment_code" value="" style="width:320px;" /><br/>
							به صورت دستی کد را کپی نموده و در برگه یا نوشته خود کپی نمائید

								<script>
                                    function pay_code(site_url) {
                                        var get_pr_id = document.getElementById("dp_product_selector").value;

                                        var download_img = document.getElementById("dp_button_image_url").value;
                                        //var download_img = jQuery("#dp_button_image_url").val()

                                        var sel = document.getElementById("dp_product_selector");
                                        var get_pr_name= sel.options[sel.selectedIndex].text;

										if(get_pr_id!=''){
                                            var link = site_url + "/?dp_checkout=" + get_pr_id;

											//var code = "<a href='" + link + "'  >خرید " + get_pr_name + "</a>";
                                            var code = "<a href='" + link + "'  ><img src='" + download_img + "' /></a>";

                                            document.getElementById("payment_code").value = code;
                                        }
                                    }
								</script>
							</td>
						</tr>
					</table>
				</div>

				<div style="padding:15px;">
					<input type="button" class="button-primary" value="قرار دادن دکمه تصویری" onclick="insert_pd_pfd_button();"/>&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="button" class="button" value="قرار دادن لينک" onclick="insert_pd_pfd_link();"/>&nbsp;&nbsp;&nbsp;&nbsp;

					<a style="font-size:0.9em;text-decoration:none;color:#555555;" href="#" onclick="tb_remove(); return false;">بستن</a>
				</div>
			</div>
		</div>
	</div>
	<?php
	}

	protected static function ipn() {
		echo "<br/><div align='center' dir='rtl' style='font-family:tahoma;font-size:12px;'><b>نتیجه تراکنش</b></div><br />";
		@session_start();
		require_once('irpul.class.php');
		$p 			= new dp_class;
        $id 		= get_option('pd_token');
		$this_script= get_option('siteurl');
		$amount 	= $_SESSION['m-amount'];
		
		$irpul_token 	= $_GET['irpul_token'];
		$decrypted 		= self::url_decrypt( $irpul_token );
		if($decrypted['status']){
			parse_str($decrypted['data'], $ir_output);
			$trans_id 	= $ir_output['tran_id'];
			$order_id 	= $ir_output['order_id'];
			$amount 	= $ir_output['amount'];
			$refcode	= $ir_output['refcode'];
			$status 	= $ir_output['status'];
			
			if($status == 'paid'){
				$result 	= self::get($id,$trans_id,$amount);
				//$result=1;
				//if( $result['res_code'] === 1 ) - line 958
				if( isset($result['http_code']) ){
					$data =  json_decode($result['data'],true);

					if( isset($data['code']) && $data['code'] === 1){
						global $wpdb;
						$table_name = $wpdb->prefix . "pd_pfd_orders";
						$order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = '$order_id' AND fulfilled = 0",$trans_id) , ARRAY_A, 0);

						$wpdb->update( $table_name, array('fulfilled' => 1), array('id' => $order["id"]));

						$table_name = $wpdb->prefix . "pd_pfd_products";
						$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$order["product_id"]) , ARRAY_A, 0);

						$wpdb->update( $table_name, array('downloads' => $product["downloads"] + 1), array('id' => $product["id"]));

						/*
						// vars we want to extract
						$fields = "protection_eligibility address_status payer_id tax payment_date payment_status first_name last_name payer_status business address_name address_street address_city address_state address_zip address_country_code address_country quantity verify_sign payer_email txn_id payment_type receiver_email receiver_id txn_type item_name mc_currency item_number residence_country custom receipt_id transaction_subject payment_fee payment_gross";
						$fields_a = explode(" ",$fields);
						foreach($fields_a as $field) {
							$trans[$field] = isset($_POST[$field]) ? $_POST[$field] : NULL;
						}
						*/

						@session_start();
						$trans = array();
						$trans["product_id"] 	= $product["id"];
						$trans["order_code"] 	= $trans_id;
						$trans["order_id"] 		= $order["id"];
						$trans["payer_email"]	= $_SESSION['email'] . ' | ' . $_SESSION['telNo'] ;
						$trans["created_at"] 	= time();

						//print_r($trans);

						// insert into transactions
						$table_name = $wpdb->prefix . "pd_pfd_transactions";
						$wpdb->insert($table_name, $trans);

						// download link
						if(get_option("pd_paypal_direct") == 'on'){
							//&linkNo = 1
							$download_link = $product["file"];
							$download_name = $product["name"];

							$expld = explode(" | ", $download_link);
							$download_link 	= $expld[0];
							$secDl 			= $expld[1];

							$download_link = "
						<a href='$download_link' target='_blank'>$download_name</a>
						<br/>لینک دوم: <a href='$secDl' target='_blank'>$download_name</a>";
						}else{
							$download_link = get_option('siteurl') . "/?dp_download=" . urlencode($trans_id);
							$download_link = "
						<a href='$download_link&dp_linkNo=1' target='_blank'>$download_link</a>
						<br/> لینک دوم: <a href='$download_link&dp_linkNo=2' target='_blank'>$download_link</a>";
						}

						// get email text
						$emailtext = get_option('pd_email_message');
						$emailtext = str_replace("[DOWNLOAD_LINK]",$download_link,$emailtext);
						$emailtext = str_replace("[PRODUCT_NAME]",$download_name,$emailtext);
						$emailtext = str_replace("[TRANSACTION_ID]",$trans_id,$emailtext);
						$emailtext = str_replace("[ORDER_ID]",$order_id,$emailtext);
						$emailtext = $emailtext . "<br /><br />
					<span style='color:red'>توجه: </span>لینک دانلود فقط برای یک بار قابل استفاده است. در صورتی که موفق به دانلود نشدید با مدیریت سایت تماس بگیرید
					<br />لينک دانلود شما:<br />" . $download_link;

						// fantastic, now send them a message

						$message = $emailtext;
						echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما 
					<font color='green'><b>مـوفق بود</b></font>
					<br/>
					<p align='right' style='margin-right:15px'>".nl2br($message)."</p>
					<a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a>
					<br/><br/>
					</div>";
						@session_start();
						$headers = "From: <no-reply@yahoo.com>\n";
						$headers .= "MIME-Version: 1.0\n";
						$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
						error_reporting(0);
						mail($_SESSION['email'],'اطلاعات پرداخت',$emailtext,$headers);
						wp_mail( $_SESSION['email'], 'اطلاعات پرداخت', $emailtext, $headers );
					}
					else{
						echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.
						<br/>
						<p align='right' style='margin-right:15px'>
						<br/>- پیش از خرید دوباره با مدیر سایت تماس گرفته و وضعیت خرید خود با شماره تراکنش $trans_id را پیگیری نمائید.
						<br/>- کد خطا : " .$data['code'] . '<br/>' . $data['status'] . "
						<br/><a href='".get_option('siteurl')."'>بازگشت به صفحه اصلي</a>
						</p>
						</div>";
					}
				}else{
					echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.
					<br/>
					<p align='right' style='margin-right:15px'>
					<br/>- پیش از خرید دوباره با مدیر سایت تماس گرفته و وضعیت خرید خود با شماره تراکنش $trans_id را پیگیری نمائید.
					<br/>- پاسخی از سرویس دهنده دریافت نشد. لطفا دوباره تلاش نمائید
					<br/><a href='".get_option('siteurl')."'>بازگشت به صفحه اصلي</a>
					</p>
					</div>";
				}
			}
			else{
				echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.
				<br/><p align='right' style='margin-right:15px'> تراکنش پرداخت نشده است<br/><a href='".get_option('siteurl')."'>بازگشت به صفحه اصلي</a></p></div>";
			}
		}
		else{
			echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.<br/><p align='right' style='margin-right:15px'> توکن صحیح نیست<br/><a href='".get_option('siteurl')."'>بازگشت به صفحه اصلي</a></p></div>";
		}
	}
	
	public static function url_decrypt($string){
		$counter = 0;
		$data = str_replace(array('-','_','.'),array('+','/','='),$string);
		$mod4 = strlen($data) % 4;
		if ($mod4) {
		$data .= substr('====', $mod4);
		}
		$decrypted = base64_decode($data);
		
		$check = array('tran_id','order_id','amount','refcode','status');
		foreach($check as $str){
			str_replace($str,'',$decrypted,$count);
			if($count > 0){
				$counter++;
			}
		}
		if($counter === 5){
			return array('data'=>$decrypted , 'status'=>true);
		}else{
			return array('data'=>'' , 'status'=>false);
		}
	}

	public static function post_data($url,$params,$token) {
		ini_set('default_socket_timeout', 15);

		$headers = array(
			"Authorization: token= {$token}",
			'Content-type: application/json'
		);

		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($handle, CURLOPT_TIMEOUT, 40);

		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($params) );
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers );

		$response = curl_exec($handle);
		//error_log('curl response1 : '. print_r($response,true));

		$msg='';
		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));

		$status= true;

		if ($response === false) {
			$curl_errno = curl_errno($handle);
			$curl_error = curl_error($handle);
			$msg .= "Curl error $curl_errno: $curl_error";
			$status = false;
		}

		curl_close($handle);//dont move uppder than curl_errno

		if( $http_code == 200 ){
			$msg .= "Request was successfull";
		}
		else{
			$status = false;
			if ($http_code == 400) {
				$status = true;
			}
			elseif ($http_code == 401) {
				$msg .= "Invalid access token provided";
			}
			elseif ($http_code == 502) {
				$msg .= "Bad Gateway";
			}
			elseif ($http_code >= 500) {// do not wat to DDOS server if something goes wrong
				sleep(2);
			}
		}

		$res['http_code'] 	= $http_code;
		$res['status'] 		= $status;
		$res['msg'] 		= $msg;
		$res['data'] 		= $response;

		if(!$status){
			//error_log(print_r($res,true));
		}
		return $res;
	}

	public static function get($token,$tran_id,$amount){
		$parameters = array(
			'method' 	    => 'verify',
			'trans_id' 		=> $tran_id,
			'amount'	 	=> $amount,
		);

		$result =  post_data('https://irpul.ir/ws.php', $parameters, $token );

		return $result;
	}
	
	protected static function get_email() {
		echo "<div align='center' dir='rtl' style='margin-top:50px;font-family:tahoma;font-size:12px;'><b>اطلاعات تکمیلی</b></div><br />";
		@session_start();
		$rand 					= rand(10,99);
		$_SESSION['captcha'] 	= $rand;
		if(get_option('pd_get_hamrah') == 'on'){
			$shomareTel = '<tr>
			<td>شماره همراه: </td><td><input type="text" name="telNo" id="telNo" required value="'.$_POST['telNo'].'" /> </td>
		</tr>';
		}else 
			$shomareTel = '';
		echo '<div align="center" dir="rtl" style="font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%"><form name="frm1" method="post">
		<table>
		<tr>
			<td>ایمیل: </td><td><input type="email" name="email" id="email" required value="'.$_POST['email'].' " /> </td>
		</tr>
		' . $shomareTel .
		'
		<tr>
		<td>لطفاً عدد '.$rand.' را وارد کنید:</td><td><input type="tel" min="10" max="99" name="captcha" required/> </td>
		</tr>
		<tr>
			<td></td><td><input type="submit" name="submit" value="پرداخت" style="font-family:tahoma"/></td>
		</tr>
		</table>
		</form>
		</div><div style="display:none">
		';
	}

	public static function var_listener() {
         if(get_query_var("dp_checkout")==NULL) {
			if(get_query_var("dp_download")==NULL) {
				if (get_query_var("pd_pfd_action") == "ipn") {
					self::ipn();
					exit();
				}
			} else {
				$id = $_GET["dp_download"];
				if($_GET['dp_linkNo']=='1'){
					$part = 0;
				}else {
					$part = 1;
				}	
				
				global $wpdb;
				$table_name = $wpdb->prefix . "pd_pfd_transactions";
				$transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = %s",$id ), ARRAY_A, 0);
				
				//print_r($transaction) ;
				
				if ($transaction==NULL) {
					die("فايل مورد نظر يافت نشد.");
				} else {
					$table_name = $wpdb->prefix . "pd_pfd_products";
					$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$transaction["product_id"]), ARRAY_A, 0);
					// get option for days
					$daysexpire = get_option('pd_expire_links_after');
					if ($daysexpire == 0) {
						// don't check
					} else {
						// check for expiry
						// transaction created at should be larger than now - x days
						$nowminus = time() - ($daysexpire*86400);
						if ($transaction["created_at"] > $nowminus) {
							// good
						} else {
							die("مدت زمان دانلود اين فايل به اتمام رسيده است.");
						}
					}

					// force download
					$expld = explode(" | ", $product['file']);
					$product['file'] = $expld[$part];

					header('Content-disposition: attachment; filename=' . basename($product["file"]));
					header('Content-Type: application/octet-stream');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Expires: 0');

					$result = wp_remote_get($product["file"]);

					echo $result['body'];
					
					die();
				}
			}
		} 
		else 
		{
			@session_start();
			if(isset($_POST['submit']) && ($_SESSION['captcha'] == $_POST['captcha']) && $_SESSION['captcha'] != '' && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)){
				$_SESSION['email'] 	= $_POST['email'];
				$telNo 				= $_SESSION['telNo']  = $_POST['telNo'];
				$product_id 		= get_query_var("dp_checkout");
				$payer_mail 		= $_POST['email'];
				
				global $wpdb;
				$table_name = $wpdb->prefix . "pd_pfd_products";
	
				// get product
				$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$product_id) , ARRAY_A, 0);
	
				// construct order
				$table_name 			= $wpdb->prefix . "pd_pfd_orders";
				$token					= get_option('pd_token');
				$resnum					= rand(10000,100000);
				$amount 				= $product["cost"];
				$product_name 			= $product["name"];
				$file_dl_link 			= $product["file"];
				$_SESSION['m-amount'] 	= $amount;
				$redirect 				= get_option('siteurl') . "/?pd_pfd_action=ipn";
				
				$parameters = array(
					'method' 		=> 'payment',
					'order_id'		=> $resnum,
					'phone' 		=> '',
					'email' 		=> $payer_mail,
					'amount' 		=> $amount,
					'product' 		=> $product_name,
					'payer_name' 	=> '',
					'mobile' 		=> $telNo,
					'callback_url' 	=> $redirect,
					'address' 		=> '',
					'description' 	=> $file_dl_link,
					'test_mode' 	=> false
				);

				$result 	= post_data('https://irpul.ir/ws.php', $parameters, $token );

				if( isset($result['http_code']) ){
					$data =  json_decode($result['data'],true);

					if( isset($data['code']) && $data['code'] === 1){
						$wpdb->insert( $table_name, array('product_id' => $product_id, 'order_code' => $resnum, 'fulfilled' => 0, 'created_at' => time(), 'cost' => $product["cost"]), array( '%d', '%s', '%d', '%d', '%s') );

						header("Location: " . $data['url']);
						exit;
					}
					else{
						echo "Error Code: ".$data['code'] . ' ' . $data['status'];
						exit;
					}
				}else{
					echo 'پاسخی از سرویس دهنده دریافت نشد. لطفا دوباره تلاش نمائید';
					exit;
				}
			}
			else{
				self::get_email();
				exit();
			}
		}
    }

    // make sure we have the paypal action listener available
	public static function register_vars($vars) {
		$vars[] = "pd_pfd_action";
		$vars[] = "dp_checkout";
		$vars[] = "dp_download";
		$vars[] = "dp_linkNo";
		return $vars; // return to wordpress
	}
}
