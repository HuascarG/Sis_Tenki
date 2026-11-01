<?php
/**
 * Config/mail.php
 * Inicializador de PHPMailer consumiendo variables de entorno.
 * Soporta Mailtrap (desarrollo) y SMTP real (producción) sin
 * cambios en el código — solo se modifica el archivo .env.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

// Autoload de Composer o inclusión manual
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    $vendorSrc = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/';
    if (file_exists($vendorSrc . 'Exception.php')) {
        require_once $vendorSrc . 'Exception.php';
        require_once $vendorSrc . 'PHPMailer.php';
        require_once $vendorSrc . 'SMTP.php';
    }
}

/**
 * Retorna una instancia de PHPMailer configurada con las variables de entorno.
 *
 * @throws MailException  Si hay un error de configuración
 * @return PHPMailer
 */
function createMailer(): PHPMailer {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new RuntimeException('El componente PHPMailer no está disponible.');
    }
    $mail = new PHPMailer(true); // true = habilita excepciones

    $mail->isSMTP();
    $mail->Host       = env('MAIL_HOST', 'sandbox.smtp.mailtrap.io');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('MAIL_USER', '');
    $mail->Password   = env('MAIL_PASS', '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) env('MAIL_PORT', 2525);
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(
        env('MAIL_FROM', 'noreply@tenki.local'),
        env('MAIL_FROM_NAME', 'TENKI')
    );

    // Debug: 0 = sin output, SMTP::DEBUG_SERVER para desarrollo
    $mail->SMTPDebug = env('APP_ENV') === 'local' ? SMTP::DEBUG_OFF : SMTP::DEBUG_OFF;

    return $mail;
}

/**
 * Envía un correo electrónico.
 *
 * @param string $toEmail   Destino
 * @param string $toName    Nombre del destino
 * @param string $subject   Asunto
 * @param string $htmlBody  Cuerpo HTML
 * @param string $textBody  Cuerpo texto plano (alternativo)
 * @return bool             true si se envió, false en caso de error
 * @throws RuntimeException Con mensaje descriptivo en caso de fallo
 */
function sendMail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {
    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (MailException $e) {
        // Registrar error sin exponer credenciales
        error_log('[TENKI Mail Error] ' . $e->getMessage());
        throw new RuntimeException('No se pudo enviar el correo. Por favor intenta más tarde.');
    }
}
