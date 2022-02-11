<?php
    
    // Funcion para elegir un numero random y crear un hash.
    function genRandHash(){
        $numRandom = rand(7,754863);
        $hash = hash('sha256',$numRandom);
        return $hash;
    }

    ///////////////////////////////
    ///// DATABASE FUNCTIONS //////
    ///////////////////////////////

    function insertarDatos($username,$email,$fname,$lname,$passHash){
        require('connecta_db_persistent.php');

        $sql = 'INSERT INTO users (username,mail,userFirstName,userLastName,passHash,creationDate,active,activationCode) VALUES (:username,:mail,:fname,:lname,:passHash,CURTIME(),:active,:actCode);';
        $usuaris = $db->prepare($sql);
        $usuaris->execute(array(":username"=>$username,":mail"=>$email,":fname"=>$fname,":lname"=>$lname,":passHash"=>$passHash,":active"=>"0",":actCode"=>genRandHash()));
    
    }

    function verificarExistencia($username,$email){
        require('connecta_db_persistent.php');
        
        $sql = 'SELECT * FROM users WHERE (UPPER(username)=UPPER(:usrFilt) OR UPPER(mail)=UPPER(:mailFilt))'; 
        $verificaExistent = $db->prepare($sql);
        $verificaExistent->execute(array(":usrFilt"=>$username,":mailFilt"=>$email));
        $NumUsersExistents = $verificaExistent->rowCount();

        return $NumUsersExistents;
    }
    
    function verificarExpiracion($userMail){
        require('connecta_db_persistent.php');

        $userMail = strtoupper($userMail);

        $sql = 'SELECT * FROM users WHERE UPPER(mail)=UPPER(:mailFilt) AND resetPassExpiry >= CURTIME()'; 
        $verificaTiempo = $db->prepare($sql);
        $verificaTiempo->execute(array(":mailFilt"=>$userMail));
        $NumUsersExistents = $verificaTiempo->rowCount();

        return $NumUsersExistents;
    }

    function updateSignIn($idUser){
        require('connecta_db_persistent.php');
        
        $sql = 'UPDATE users SET lastSignIn = CURTIME() WHERE iduser = :iduser';
        $update = $db->prepare($sql);
        $update->execute(array(":iduser"=>$idUser));
    }
 
    function getUserCode($type,$userMail){
        require('connecta_db_persistent.php');

        if($type == 1){
            // Para pillar el codigo de activacion:
            $sql = 'SELECT activationCode FROM users WHERE UPPER(mail) = :mail';
            $consulta = $db->prepare($sql);
            $consulta->execute(array(":mail"=>$userMail));

            foreach($consulta as $registro){
                $userHash = $registro['activationCode'];
            }
        }elseif($type == 2){
            // Para pillar el codigo de restauracion de contraseña:
            $sql = 'SELECT resetPassCode FROM users WHERE UPPER(mail) = :mail OR UPPER(username) = :user'; 
            $consulta = $db->prepare($sql);
            $consulta->execute(array(":mail"=>$userMail, ":user"=>$userMail));

            foreach($consulta as $registro){
                $userHash = $registro['activationCode'];
            }
        }
        return $userHash;
    } 

    function insertUserCode($type,$userMail,$hash){
        require('connecta_db_persistent.php');
        //Lo del type aqui queda raro pero al final no he hecho type 1 como en la funcion de borrar
        if($type == 2){
            // Insertar el codigo de restauracion de contraseña:
            $sql = 'UPDATE users SET resetPassCode = :code, resetPassExpiry = CURTIME() + INTERVAL 30 MINUTE WHERE UPPER(mail) = :mail;';
            $update = $db->prepare($sql);
            $update->execute(array("code"=>$hash,":mail"=>$userMail));
        }
        
    } 

    function eliminarUserCode($type,$userMail,$hash){
        require('connecta_db_persistent.php');
        $userMail = strtoupper($userMail);

        if($type == 1){
            // Borrar el codigo de activacion:
            $sql = 'UPDATE users SET activationCode = NULL WHERE UPPER(mail) = :mail AND activationCode = :code';
            $update = $db->prepare($sql);
            $update->execute(array(":mail"=>$userMail, ":code"=>$hash));
        }elseif($type == 2){
            // Borrar el codigo de restauracion de contraseña:
            $sql = 'UPDATE users SET resetPassCode = NULL, resetPassExpiry = NULL WHERE UPPER(mail) = :mail AND resetPassCode = :code;';
            $update = $db->prepare($sql);
            $update->execute(array(":mail"=>$userMail, "code"=>$hash,));
        }
    } 

    function datosUsuario($usuario){
        require('connecta_db_persistent.php');
        $iduser = ''; $username = ''; $userFirstName = ''; $userLastName = ''; $hash = ''; $activo = '';
        
        $sql = 'SELECT iduser,CONCAT(UPPER(SUBSTRING(userFirstName,1,1)),LOWER(SUBSTRING(userFirstName,2))) as userFirstName,CONCAT(UPPER(SUBSTRING(userLastName,1,1)),LOWER(SUBSTRING(userLastName,2))) as userLastName,username,passhash,active 
                FROM `users` 
                WHERE (UPPER(username)=:filtre OR UPPER(mail)=:filtre) 
                LIMIT 1';
        $consulta = $db->prepare($sql);
        $consulta->execute(array(":filtre"=>$usuario,));

        $res = $consulta->rowCount();
        
        foreach($consulta as $registro){
            $iduser = $registro['iduser'];
            $username = $registro['username'];
            $userFirstName = $registro['userFirstName'];
            $userLastName = $registro['userLastName'];
            $hash = $registro['passhash'];
            $activo = $registro['active'];
        }
        $datosUser = array('iduser'=>$iduser,
                            'username'=>$username,
                            'userFirstName'=>$userFirstName,
                            'userLastName'=>$userLastName,
                            'passhash'=>$hash,
                            'active'=>$activo,
                            'existe'=>$res);
        return $datosUser; 
    }
    
    function activarUsuario($code,$mail){
        require('connecta_db_persistent.php');
        
        $sql = 'UPDATE users SET active = 1, activationDate = CURTIME() WHERE activationCode = :code AND mail = :mail';
        $update = $db->prepare($sql);
        $update->execute(array(":code"=>$code, ":mail"=>$mail));
    }

    function camviarContraseña($userMail,$code,$passHash){
        require('connecta_db_persistent.php');
        
        $userMail = strtoupper($userMail);

        $sql = 'UPDATE users SET passHash = :contra WHERE resetPassCode = :code AND UPPER(mail) = :mail AND resetPassExpiry >= CURTIME()';
        $update = $db->prepare($sql);
        $update->execute(array(":contra"=>$passHash, ":code"=>$code, ":mail"=>$userMail));
    }

    ////////////////////////////////
    ////// MAILING FUNCTIONS ///////
    ////////////////////////////////

    use PHPMailer\PHPMailer\PHPMailer;
    
    function mailActivateUser($userMail){
        $userMailUpp = strtoupper($userMail);
        $datosUser = datosUsuario($userMailUpp);
        
        $userCode = getUserCode(1,$userMailUpp);

        $contMail = contenidoMail(1,$userMail,$userCode,$datosUser);        
        sendMail($contMail['assunto'],$contMail['mensaje'],$userMail);
    }

    function mailResetPassword($userMail){
        $userMailUpp = strtoupper($userMail);
        $datosUser = datosUsuario($userMailUpp);

        $userCode = genRandHash();
        insertUserCode('2',$userMailUpp,$userCode);

        $contMail = contenidoMail(2,$userMail,$userCode,$datosUser);
        sendMail($contMail['assunto'],$contMail['mensaje'],$userMail);
    }

    function resetPasswordNotify($userMail){
        $userMailUpp = strtoupper($userMail);
        $datosUser = datosUsuario($userMailUpp);

        $contMail = contenidoMail(3,$userMail,'',$datosUser);
        sendMail($contMail['assunto'],$contMail['mensaje'],$userMail);
    }


    function sendMail($assunto,$mensaje,$userMail){ 
        
        require '../vendor/autoload.php';

        $mail = new PHPMailer();
        $mail->IsSMTP();

        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        
        //Credencials del compte GMAIL
        $mail->Username = '';
        $mail->Password = '';

        //Dades del correu electrònic
        $mail->SetFrom('accounts@eduhacks.com','Eduhacks');
        $mail->Subject = $assunto;
        $mail->MsgHTML($mensaje);
        //$mail->addAttachment("fitxer.pdf");
        
        //Destinatari
        $address = $userMail;
        $mail->AddAddress($address, 'Eduhacks');

        //Enviament
        $result = $mail->Send();
        if(!$result){
            echo 'Error: ' . $mail->ErrorInfo;
        }else{
            header("Location:../index.php?redirected=0");
            exit;
        }
    }

    function contenidoMail($tipo,$userMail,$userCode,$datosUser){
        $assunto = '';
        $mensaje = '';
        
        if($tipo == 1){
            // Correo de activacion
            $assunto = 'Activacion cuenta Eduhacks';
            $mensaje = '
            Buenas Sr./Sra. '.$datosUser['userFirstName']." ".$datosUser['userLastName'].',
            <br><br> 
            Hemos recivido una solicitud de activacion de su cuenta de Eduhacks vinculada a su correo electronico. 
            <b>Si ha sido usted</b>, por favor active su cuenta con las intrucciones que vera en la parte inferior del correo. <br>
            <b>Si no ha sido usted</b>, no hace falta que haga caso a este correo ya que su cuenta no esta activa, pero quiere decir que alguien ha introducido su correo en <a href="#">Eduhacks</a>. <br>
            <br><br> 
            Puede activar su cuenta de eduhacks haga click 
            <a href="http://127.0.0.1/Eduhacks-Practica2/php/activateAcc.php?code='.$userCode.'&mail='.$userMail.'">aqui</a> 
            <br><br> 
            En caso de no poder acceder por el vinculo de arriba, haga click en este enlace: 
            http://127.0.0.1/Eduhacks-Practica2/php/activateAcc.php?code='.$userCode.'&mail='.$userMail.'
            ';
        }elseif($tipo == 2){
            // Correo de reset password
            $assunto = 'Cambio de contraseña Eduhacks';
            $mensaje = '
            Buenas Sr./Sra. '.$datosUser['userFirstName']." ".$datosUser['userLastName'].',
            <br><br> 
            Hemos recivido su solicitud de cambio de contraseña para su cuenta de Eduhacks vinculada a su correo electronico. 
            <b>Si ha sido usted</b>, siga las intrucciones que vera en la parte inferior del correo. <br>
            <b>Si no ha sido usted</b>, tenga cuidado que alguien quiere cambiar su contraseña, aunque sin este codigo de verificacion no podra realizar el cambio. <br>
            <br><br> 
            Para ir al cambio de contraseña haga click 
            <a href="http://127.0.0.1/Eduhacks-Practica2/php/resetPassword.php?code='.$userCode.'&mail='.$userMail.'">aqui</a> 
            <br><br> 
            En caso de no poder acceder por el vinculo de arriba, haga click en este enlace: 
            http://127.0.0.1/Eduhacks-Practica2/php/resetPassword.php?code='.$userCode.'&mail='.$userMail.'
            ';
        }elseif($tipo == 3){
            // Notificacion de reset password
            $assunto = 'Cambio reciente de contraseña en Eduhacks';
            $mensaje = '
            Buenas Sr./Sra. '.$datosUser['userFirstName']." ".$datosUser['userLastName'].',
            <br><br> 
            Se ha cambiado la contraseña de su cuenta de eduhacks. <br> 
            <b>Si ha sido usted</b>, no hace falta que haga caso a este correo. <br> 
            <b>Si no ha sido usted</b>, tenga cuidado que alguien ha cambiado la contraseña de su cuenta. <br> 
            ';
        }
        return array('assunto' => $assunto, 'mensaje' => $mensaje);
    }