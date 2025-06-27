<?php
/**
 * Plugin Name: UVT GoogleAuth
 * Description: Plugin para integrar autenticación con Google OAuth en WordPress con endpoints REST
 * Version: 1.0.2
 * Author: Diseno Univirtual
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class GoogleOAuthPlugin {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Cargar configuración
        $this->client_id = get_option('google_oauth_client_id', '');
        $this->client_secret = get_option('google_oauth_client_secret', '');
        $this->redirect_uri = site_url('/wp-json/google-oauth/v1/callback');
    }
    
    public function init() {
        // Inicialización del plugin
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        register_rest_route('google-oauth/v1', '/auth-url', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_auth_url'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('google-oauth/v1', '/callback', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_callback'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('google-oauth/v1', '/verify-token', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_token'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('google-oauth/v1', '/user-data', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => array($this, 'check_jwt_token')
        ));
    }
    
    /**
     * Generar URL de autenticación de Google
     */
    public function get_auth_url($request) {
        if (empty($this->client_id)) {
            return new WP_Error('missing_config', 'Google OAuth no está configurado', array('status' => 500));
        }
        
        $state = wp_generate_password(32, false);
        set_transient('google_oauth_state_' . $state, true, 600); // 10 minutos
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        );
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        return rest_ensure_response(array(
            'auth_url' => $auth_url,
            'state' => $state
        ));
    }
    
    /**
     * Manejar callback de Google
     */
    public function handle_callback($request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');
        
        if ($error) {
            return new WP_Error('oauth_error', 'Error de OAuth: ' . $error, array('status' => 400));
        }
        
        if (!$code || !$state) {
            return new WP_Error('missing_params', 'Parámetros faltantes', array('status' => 400));
        }
        
        // Verificar state
        if (!get_transient('google_oauth_state_' . $state)) {
            return new WP_Error('invalid_state', 'State inválido', array('status' => 400));
        }
        
        delete_transient('google_oauth_state_' . $state);
        
        // Intercambiar código por token
        $token_data = $this->exchange_code_for_token($code);
        if (is_wp_error($token_data)) {
            return $token_data;
        }
        
        // Obtener información del usuario
        $user_info = $this->get_google_user_info($token_data['access_token']);
        if (is_wp_error($user_info)) {
            return $user_info;
        }
        
        // Crear o encontrar usuario
        $user = $this->create_or_get_user($user_info);
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Generar JWT token
        $jwt_token = $this->generate_jwt_token($user);
        
        // Redirigir al frontend con el token
        $frontend_url = get_option('google_oauth_frontend_url', home_url());
        $redirect_url = add_query_arg(array(
            'token' => $jwt_token,
            'user_id' => $user->ID
        ), $frontend_url . '/auth/callback');
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Intercambiar código por token de acceso
     */
    private function exchange_code_for_token($code) {
        $token_url = 'https://oauth2.googleapis.com/token';
        
        $params = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded')
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('token_error', $data['error_description'], array('status' => 400));
        }
        
        return $data;
    }
    
    /**
     * Obtener información del usuario de Google
     */
    private function get_google_user_info($access_token) {
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $response = wp_remote_get($user_info_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $user_data = json_decode($body, true);
        
        if (isset($user_data['error'])) {
            return new WP_Error('userinfo_error', $user_data['error']['message'], array('status' => 400));
        }
        
        return $user_data;
    }
    
    /**
     * Crear o obtener usuario de WordPress
     */
    private function create_or_get_user($google_user) {
        $email = $google_user['email'];
        
        // Buscar usuario existente por email
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Actualizar metadatos de Google
            update_user_meta($user->ID, 'google_id', $google_user['id']);
            update_user_meta($user->ID, 'google_picture', $google_user['picture']);
            return $user;
        }
        
        // Crear nuevo usuario
        $username =  $google_user['email'];
        
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'first_name' => $google_user['given_name'],
            'last_name' => $google_user['family_name'],
            'display_name' => $google_user['name'],
            'role' => 'customer' // Rol por defecto para WooCommerce
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Guardar metadatos de Google
        update_user_meta($user_id, 'google_id', $google_user['id']);
        update_user_meta($user_id, 'google_picture', $google_user['picture']);
        update_user_meta($user_id, 'oauth_provider', 'google');
        
        return get_user_by('ID', $user_id);
    }
    
    /**
     * Generar nombre de usuario único
     */
    private function generate_unique_username($email) {
        $base_username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $username = $base_username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Generar JWT token
     */
    private function generate_jwt_token($user) {
        $issued_at = time();
        $expiration = $issued_at + (24 * 60 * 60); // 24 horas
        
        $payload = array(
            'iss'  => site_url(),
            'iat'  => $issued_at,
            'nbf'  => $issued_at,
            'exp'  => $expiration,
            'email' => $user->user_email,
            'id'    => $user->ID
        );
        
        return $this->jwt_encode($payload);
    }
    
    /**
     * Verificar token JWT
     */
    public function verify_token($request) {
        $token = $request->get_param('token');
        
        if (!$token) {
            return new WP_Error('missing_token', 'Token faltante', array('status' => 400));
        }
        
        $payload = $this->jwt_decode($token);
        
        if (!$payload) {
            return new WP_Error('invalid_token', 'Token inválido', array('status' => 401));
        }
        
        $user = get_user_by('ID', $payload['id']);
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Usuario no encontrado', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'valid' => true,
            'user' => array(
                'id' => $user->ID,
                'email' => $user->user_email,
                'name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            )
        ));
    }
    
    /**
     * Obtener datos del usuario autenticado
     */
    public function get_user_data($request) {
        $user_id = $request->get_param('user_id');
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Usuario no encontrado', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'google_picture' => get_user_meta($user->ID, 'google_picture', true),
            'roles' => $user->roles
        ));
    }
    
    /**
     * Verificar JWT token para permisos
     */
    public function check_jwt_token($request) {
        $auth_header = $request->get_header('Authorization');
        
        if (!$auth_header) {
            return false;
        }
        
        $token = str_replace('Bearer ', '', $auth_header);
        $payload = $this->jwt_decode($token);
        
        if (!$payload) {
            return false;
        }
        
        // Agregar user_id al request para uso posterior
        $request->set_param('user_id', $payload['user_id']);
        
        return true;
    }
    
    /**
     * Codificar JWT (versión simplificada)
     */
    private function jwt_encode($payload) {
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload = json_encode($payload);
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $this->get_jwt_secret(), true);
        $base64_signature = $this->base64url_encode($signature);
        
        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }
    
    /**
     * Decodificar JWT
     */
    private function jwt_decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        
        $decoded_header = json_decode($this->base64url_decode($header), true);
        $decoded_payload = json_decode($this->base64url_decode($payload), true);
        
        // Verificar firma
        $expected_signature = hash_hmac('sha256', $header . '.' . $payload, $this->get_jwt_secret(), true);
        $expected_signature = $this->base64url_encode($expected_signature);
        
        if ($signature !== $expected_signature) {
            return false;
        }
        
        // Verificar expiración
        if (isset($decoded_payload['exp']) && time() > $decoded_payload['exp']) {
            return false;
        }
        
        return $decoded_payload;
    }
    
    /**
     * Obtener secreto JWT
     */
    private function get_jwt_secret() {
        $secret = 'secret';
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('google_oauth_jwt_secret', $secret);
        }
        return $secret;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            'Google OAuth Settings',
            'Google OAuth',
            'manage_options',
            'google-oauth-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Inicializar configuración de admin
     */
    public function admin_init() {
        register_setting('google_oauth_settings', 'google_oauth_client_id');
        register_setting('google_oauth_settings', 'google_oauth_client_secret');
        register_setting('google_oauth_settings', 'google_oauth_frontend_url');
        
        add_settings_section(
            'google_oauth_main',
            'Configuración de Google OAuth',
            null,
            'google-oauth-settings'
        );
        
        add_settings_field(
            'google_oauth_client_id',
            'Client ID',
            array($this, 'client_id_field'),
            'google-oauth-settings',
            'google_oauth_main'
        );
        
        add_settings_field(
            'google_oauth_client_secret',
            'Client Secret',
            array($this, 'client_secret_field'),
            'google-oauth-settings',
            'google_oauth_main'
        );
        
        add_settings_field(
            'google_oauth_frontend_url',
            'Frontend URL',
            array($this, 'frontend_url_field'),
            'google-oauth-settings',
            'google_oauth_main'
        );
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Google OAuth Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('google_oauth_settings');
                do_settings_sections('google-oauth-settings');
                submit_button();
                ?>
            </form>
            
            <h2>Endpoints disponibles:</h2>
            <ul>
                <li><strong>Obtener URL de autenticación:</strong> <code>GET /wp-json/google-oauth/v1/auth-url</code></li>
                <li><strong>Callback (automático):</strong> <code>GET /wp-json/google-oauth/v1/callback</code></li>
                <li><strong>Verificar token:</strong> <code>POST /wp-json/google-oauth/v1/verify-token</code></li>
                <li><strong>Datos del usuario:</strong> <code>GET /wp-json/google-oauth/v1/user-data</code></li>
            </ul>
            
            <h2>Configuración en Google Cloud Console:</h2>
            <p><strong>Redirect URI:</strong> <code><?php echo $this->redirect_uri; ?></code></p>
        </div>
        <?php
    }
    
    public function client_id_field() {
        $value = get_option('google_oauth_client_id', '');
        echo '<input type="text" name="google_oauth_client_id" value="' . esc_attr($value) . '" size="50" />';
    }
    
    public function client_secret_field() {
        $value = get_option('google_oauth_client_secret', '');
        echo '<input type="password" name="google_oauth_client_secret" value="' . esc_attr($value) . '" size="50" />';
    }
    
    public function frontend_url_field() {
        $value = get_option('google_oauth_frontend_url', home_url());
        echo '<input type="url" name="google_oauth_frontend_url" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">URL de tu aplicación Next.js (ej: http://localhost:3000)</p>';
    }
}

// Inicializar el plugin
new GoogleOAuthPlugin();

// Hook de activación
register_activation_hook(__FILE__, 'google_oauth_plugin_activate');

function google_oauth_plugin_activate() {
    // Crear tabla para almacenar tokens si es necesario
    flush_rewrite_rules();
}

// Hook de desactivación
register_deactivation_hook(__FILE__, 'google_oauth_plugin_deactivate');

function google_oauth_plugin_deactivate() {
    flush_rewrite_rules();
}
?>