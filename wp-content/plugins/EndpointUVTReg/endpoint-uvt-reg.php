<?php
/**
 * Plugin Name:       EndPoint UVT
 * Plugin URI:        #
 * Description:       Añade un endpoint personalizado a la API REST para registrar usuarios con campos personalizados (SCF/ACF) desde un frontend headless (ej. Next.js).
 * Version:           1.0.0
 * Author:            Diseno Univirtual
 * Author URI:        https://univirtual.utp.edu.co
 * License:           GPL v2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       endpoint-reg
 * Domain Path:       /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Se Registra los endpoints personalizados en la API REST de WordPress.
 *
 * Se ejecuta en la acción 'rest_api_init'.
 */
add_action( 'rest_api_init', 'merp_registrar_endpoints' );

function merp_registrar_endpoints() {

    // Endpoint para manejar el envío del formulario de registro
    register_rest_route( 'custom/v1', '/register', array(
        'methods'             => 'POST', // Solo acepta peticiones POST
        'callback'            => 'merp_manejar_registro_usuario', // Función que procesa la petición
        'permission_callback' => 'merp_chequear_permisos_registro', // Función que verifica si la petición es válida (nonce)
        'args'                => array( // Define y valida los argumentos esperados en la petición
            'firstName' => array(
                'required'          => true, // Campo obligatorio
                'sanitize_callback' => 'sanitize_text_field', 
                'validate_callback' => function($param, $request, $key) { // Validación básica
                    return !empty(trim($param)); // No puede estar vacío
                }
            ),
            'lastName' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param, $request, $key) {
                    return !empty(trim($param));
                }
            ),
            'email' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_email', // Limpia específicamente para emails
                'validate_callback' => function($param, $request, $key) {
                    return is_email($param); // Valida que sea un formato de email válido
                }
            ),
            'password' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field', // Sanitización básica (WP se encarga del hash)
                'validate_callback' => function($param, $request, $key) {
                    // Podrías añadir validación de fortaleza aquí si lo deseas
                    return !empty($param); // No puede estar vacío
                }
            ),
            
            'documentType' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param, $request, $key) {

                    return !empty(trim($param));
                }
            ),
                'documentNumber' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field', 
                'validate_callback' => function($param, $request, $key) {

                    return !empty(trim($param));
                }
            ),
            // seguridad CSRF
            'nonce' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                 'validate_callback' => function($param, $request, $key) {
                    return !empty($param);
                }
            )
        ),
    ) );

    // Endpoint para que el frontend obtenga un nonce válido antes de enviar el registro
     register_rest_route( 'custom/v1', '/nonce', array(
        'methods' => 'GET', // Solo acepta peticiones GET
        'callback' => function() {
            // Genera un nonce específico para la acción de la API REST
            $nonce = wp_create_nonce('wp_rest');
            // Devuelve el nonce en formato JSON
            return new WP_REST_Response(array(
                'success' => true,
                'nonce' => $nonce
            ), 200); // 200 OK
        },
        'permission_callback' => '__return_true' // Cualquiera puede solicitar un nonce (es público pero de un solo uso)
    ));
}

/**
 * Verifica los permisos para el endpoint de registro.
 *
 * Principalmente, valida el nonce enviado en la petición.
 *
 * @param WP_REST_Request $request Objeto de la petición REST.
 * @return bool|WP_Error True si el permiso es concedido, WP_Error si es denegado.
 */
function merp_chequear_permisos_registro( WP_REST_Request $request ) {
    $nonce = $request->get_param('nonce'); // Obtiene el nonce del cuerpo de la petición POST

    // Alternativa: Obtener nonce de la cabecera (si el frontend lo envía así)
    // $nonce = $request->get_header('X-WP-Nonce');

    // Verifica que el nonce exista y sea válido para la acción 'wp_rest'
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        // Si el nonce es inválido o no se proporcionó, deniega el acceso
        return new WP_Error(
            'rest_forbidden_context', // Código de error estándar
            __( 'El token de seguridad (nonce) es inválido o ha expirado.', 'mi-endpoint-registro-personalizado' ), // Mensaje de error (traducible)
            array( 'status' => 403 ) // Código de estado HTTP 403 Forbidden
        );
    }

    // Si el nonce es válido, permite que la petición continúe hacia el callback principal
    return true;
}


/**
 * Maneja la lógica principal del registro de usuario.
 *
 * Se ejecuta después de que 'merp_chequear_permisos_registro' devuelve true.
 *
 * @param WP_REST_Request $request Objeto de la petición REST.
 * @return WP_REST_Response Respuesta JSON al frontend.
 */
function merp_manejar_registro_usuario( WP_REST_Request $request ) {
    // Obtiene todos los parámetros ya validados y sanitizados definidos en 'args'
    $params = $request->get_params();

    $email = $params['email'];
    $password = $params['password'];
    $first_name = $params['firstName'];
    $last_name = $params['lastName'];
    $document_type = $params['documentType'];
    $document_number = $params['documentNumber'];

    // --- Validación Adicional Específica del Negocio ---

    // 1. Verificar si el correo electrónico ya está registrado
    if ( email_exists( $email ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'code' => 'email_exists',
            'message' => __( 'Este correo electrónico ya está registrado. Por favor, intenta iniciar sesión o usa otro correo.', 'mi-endpoint-registro-personalizado' )
        ), 409 ); // 409 Conflict - Indica que el recurso ya existe
    }

    // 2. Crear un nombre de usuario único (email por simplicidad)

    $username = $email;
    if ( username_exists( $username ) ) {

         return new WP_REST_Response( array(
            'success' => false,
            'code' => 'username_exists',
            'message' => __( 'El nombre de usuario derivado del correo ya existe.', 'mi-endpoint-registro-personalizado' )
        ), 409 ); // 409 Conflict
    }

    // --- Creación del Usuario ---
    $user_data = array(
        'user_login'   => $username,   // Nombre de usuario para login
        'user_email'   => $email,      // Email del usuario
        'user_pass'    => $password,   // Contraseña (wp_create_user se encarga del hash)
        'first_name'   => $first_name, // Nombre
        'last_name'    => $last_name,  // Apellido
        'display_name' => $first_name . ' ' . $last_name, // Nombre a mostrar públicamente
        'role'         => 'customer' // Rol por defecto para clientes de WooCommerce
    );

    // Intenta crear el usuario en la base de datos
    $user_id = wp_insert_user( $user_data );


    // Verificar si hubo algún error durante la creación del usuario
    if ( is_wp_error( $user_id ) ) {
        // Si hubo un error, devuelve el mensaje de error de WordPress
        return new WP_REST_Response( array(
            'success' => false,
            'code'    => $user_id->get_error_code(), // Código interno del error de WP
            'message' => $user_id->get_error_message() // Mensaje del error de WP
        ), 500 ); // 500 Internal Server Error - Algo falló en el servidor
    }

    // --- Guardar Campos Personalizados (SCF / ACF) ---
    // ¡¡¡ IMPORTANTE !!!
    // Reemplaza 'field_xxxxxxxxxxxxx1' y 'field_yyyyyyyyyyyyy2' con las KEYS REALES
    // de tus campos personalizados de SCF/ACF. Las encuentras editando el grupo
    // de campos en el administrador de WordPress. Tienen un formato como 'field_60abcdef12345'.

    $document_type_field_key = 'field_681408b63fe9e'; 
    $document_number_field_key = 'field_68141172eb770'; 


    update_field( $document_type_field_key, $document_type, 'user_' . $user_id );
    update_field( $document_number_field_key, $document_number, 'user_' . $user_id );

    // --- Acciones Post-Registro (Opcional) ---

    // --- Respuesta Exitosa ---
    // Devuelve una respuesta indicando que el usuario fue creado correctamente.
    return new WP_REST_Response( array(
        'success' => true,
        'message' => __( '¡Usuario registrado exitosamente! Revisa tu correo para más información.', 'mi-endpoint-registro-personalizado' ),
        'user_id' => $user_id // Devuelve el ID del usuario creado
    ), 201 ); // 201 Created 
}

?>
