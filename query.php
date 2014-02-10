<?php
set_error_handler ( 'Error_Handler' );

function Error_Handler ( $errno, $errstr, $errfile, $errline, $errcontext )
{
}

class query {


public $info, $players, $rules, $mod;
private $socket, $packet, $challenge, $timeout;

//----------------------------------------------------------
//                  PUBLIC FUNCTIONS
public function SetServer ( $ip, $port ) {
    $this->ip       =   $ip;
    $this->port     =   $port;
    $this->timeout  =   500000;
    
    }
public function Info () {
    $this->Connect();
    // Send the query to the server.
    $this->WritePacket( 'TSource Engine Query' );
    // Read whole packet returned from server.
    $this->ReadPacket();
    $this->Disconnect();
    // Source returns with an 'I'.
    if( $this->packet[4] == 'I' ){
        // Remove Junk.
        $this->Read(6);
        $this->SourceInfo();
        }
    else
        {
        // HL1 and CS1.6 old returns a 'm'.
        if( $this->packet[4] == 'm' ){
        // Remove Junk.
        $this->Read(5);
        $this->HL1Info();
        }
        // If server dose not return 'I' or 'm' trigger unknown game error.
        else trigger_error( 'Unknown Game type, Can not Query. Type:'.$this->packet[4],E_USER_ERROR );
        }
        }
 
 public function Rules ()    {
     $this->Connect();
     $this->ChallengeRules();
     // Loops though 100 rules TODO, get number of rules first.
     for ( $i = 0; $i <  100; $i++ )
     {
         $temp = $this->Read_String();
         // Remove null values from names of rules.
         $temp = trim($temp, "0");
         if( $temp != '' ){
         $this->rules[$temp] = $this->Read_String();
         
         }
     } 
     $this->Disconnect(); 
 }
 
 public function Players ()  {
     $this->Connect();
     $this->ChallengePlayers();
     //Remove Junk
     $this->Read( 5 );
     //Read player count
     $players = $this->Read_Int_8();
     //Loop though each player 
     for ( $i = 0; $i <  $players; $i++ )
     {
         $id    =   $this->Read_Int_8();
         $name  =   $this->Read_String();
         $kills =   $this->Read_Int_32();
         $time  =   $this->Read_Float_32();
         // If player has a name add them to the array
         if($name!=''){
             $this->players[$i][ 'name' ]   = $name;
             $this->players[$i][ 'kills' ]  = $kills;
             $this->players[$i][ 'time' ]   = gmdate("H:i:s",round($time, 0));
             
             }
     }     
     
     }

//-------------------------------------------------------------------
//              STEAMWORKERS

private function SourceInfo ()  {
    // Splits returned string into an array
    $this->info[ 'name' ]         =      $this->Read_String();
    $this->info[ 'map' ]          =      $this->Read_String();
    $this->info[ 'dir' ]          =      $this->Read_String();
    $this->info[ 'description' ]  =      $this->Read_String();
    $this->info[ 'appid']         =      $this->Read_Int_16();
    $this->info[ 'players' ]      =      $this->Read_Int_8();
    $this->info[ 'max_players' ]  =      $this->Read_Int_8();
    $this->info[ 'bots' ]         =      $this->Read_Int_8();
    $this->info[ 'dedicated' ]    =      $this->Read();
    $this->info[ 'os' ]           =      $this->Read();
    $this->info[ 'password' ]     =      $this->Read_Int_8();
    $this->info[ 'secure' ]       =      $this->Read_Int_8();
    $this->info[ 'version' ]      =      $this->Read_Int_8(); 
    
    
    }

private function HL1Info () {
    // Splits returned string into an array
    $this->info[ 'address' ]      =       $this->Read_String();
    $this->info[ 'name' ]         =       $this->Read_String();
    $this->info[ 'map' ]          =       $this->Read_String();
    $this->info[ 'dir' ]          =       $this->Read_String();
    $this->info[ 'description' ]  =       $this->Read_String();
    $this->info[ 'players' ]      =       $this->Read_Int_8();
    $this->info[ 'max_players' ]  =       $this->Read_Int_8();
    $this->info[ 'version' ]      =       $this->Read_Int_8();
    $this->info[ 'dedicated' ]    =       $this->Read();
    $this->info[ 'os' ]           =       $this->Read();
    $this->info[ 'password' ]     =       $this->Read_Int_8();
    $this->info[ 'mod' ]          =       $this->Read_Int_8();
    if($this->info[ 'mod' ])
        {
        // Read any mod information if set.
    $this->mod[ 'url' ]           =       $this->Read_String();
    $this->mod[ 'download' ]      =       $this->Read_String();
        // Remove Junk.
    $this->Read_Int_8();
    $this->mod[ 'version' ]       =       $this->Read_Int_8();
    $this->mod[ 'size' ]          =       $this->Read_Int_8();
    $this->mod[ 'serverside' ]    =       $this->Read_Int_8();
    $this->mod[ 'customdll' ]     =       $this->Read_Int_8();
        }
    // Remove junk.
    $this->Read(6);
    $this->info[ 'secure' ]       =       $this->Read_Int_8();
    $this->info[ 'bots' ]         =       $this->Read_Int_8();

    
}

private function ChallengePlayers ()   {
    // Ask server for a challenge. 
    fwrite($this->socket,"\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
    $this->ReadPacket();
    // If server returns 'D' it is an CS1.6 of HL1 old type
    if( $this->packet[4] == 'D' ){
        $this->WritePacket( 'players' );
        $this->ReadPacket();
        $this->Disconnect();
        
        }
        else
        {
        // If server responds with a challenge send it back with the player command.
        fwrite($this->socket,"\xFF\xFF\xFF\xFF\x55".$this->challenge);
        $this->ReadPacket();
        $this->Disconnect();
         
        }
       
        
}

private function ChallengeRules()   {
    // Ask server for a challenge. 
    fwrite($this->socket,"\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
        $this->ReadPacket();
        // If server returns 'D' it is an CS1.6 of HL1 old type.
        if( $this->packet[4]=='D' ){
        fwrite($this->socket,"\xFF\xFF\xFF\xFF\x56\xFF\xFF\xFF\xFF");
        $this->ReadPacket();
        }
        else
        {
       
        fwrite($this->socket,"\xFF\xFF\xFF\xFF\x56".$this->challenge);
        $this->ReadPacket();
        }
        
      
        
}

//--------------------------------------
//              DATAWORKERS

private function ReadPacket ()   {
    // Test to see if the socket has timed out waiting for data.
    $this->TestTimeout();
    // Read first byte from buffer.
    $data = fread ($this->socket, 1);
    // Test timeout again as UDP sockets sometimes won't time out untill read from.
    $this->TestTimeout();
    // First byte tells us if the packet has been split into mulitple datagrams.
    switch( ord( $data ) )
    {
        // 255 is 1 packet, reads remander of packet from buffer to string.
        case 255:
            $status = Socket_Get_Status( $this->socket );
            $data  .= fread( $this->socket, $status[ 'unread_bytes' ] );
            break;
        // 254 is more than 1 packet feed data into the split packet function.
        case 254:
            $data = $this->ReadSplitPacket( 7 );
            break;
        case 0:
        // 0 is null packet return with nothing.
            return false;
    }
        
    // IF the 4 byte of a returned packet is 'A' store buffer as a challenge response.
    if( $data[ 4 ] == 'A' && SubStr( $data, 0, 5 ) == "\xFF\xFF\xFF\xFFA" )
                {
        $this->challenge = SubStr( $data, 5 );
        return;
        
        }
       $this->packet = $data;
}

private function ReadSplitPacket( $bytes ) {
                
        return $data;
}

        
private function WritePacket ( $command )  {
    // Pad command.
    $command = "\xFF\xFF\xFF\xFF" . $command . "\x00";
    // Send command.
    fwrite($this->socket, $command);
    // Test timeout after sending data to socket.
    $this->TestTimeout();
        
        }
private function Connect ()  {
    // Open the socket.
    $this->socket = fsockopen("udp://" . $this->ip, $this->port, $errno, $errstr, 1) or
    // If socket can not open tigger error.
    trigger_error('Socket Could Not Be Made.',E_USER_ERROR); 
    // Set the timeout on the socket.
    $this->SetTimeout();
    
    }
private function Disconnect ()   {
    // Close the socket. DERP
    fclose($this->socket);    
    
    }
private function SetTimeout ()   {
        if(!isset($this->socket)){trigger_error('Socket Could Not Be Made.',E_USER_ERROR);}
        socket_set_timeout($this->socket,0,$this->timeout);
}
private function TestTimeout ()  {
        $status = socket_get_status($this->socket);
        if ($status['timed_out'] == 1){trigger_error('Socket Timeout.', E_USER_ERROR);}
        if ($status['unread_bytes'] == 1){trigger_error('Socket Timeout.', E_USER_ERROR);}
}
private function Read_Float_32 () {
        $string = substr( $this->packet, 0, 4 );
        $this->packet = substr( $this->packet, 4 );
        $float = unpack('ffloat', $string);
        return $float['float'];
}
private function Read_32 () {
        $string = substr( $this->packet, 0, 4 );
        $this->packet = substr( $this->packet, 4 );
        $float = unpack($string, "l");
        return $float['float'];
}
private function Read_Int_32 ()   {
        $string = substr( $this->packet, 0, 4 );
        $this->packet = substr( $this->packet, 4 );
        $int = unpack('Sint',$string);
        return $int['int'];
}
private function Read_Int_16 ()   {
        $string = substr( $this->packet, 0, 2 );
        $this->packet = substr( $this->packet, 2 );
        $int = unpack('Sint',$string);
        return $int['int'];
}
private function Read_Int_8 ()    {
        return ord( $this->Read() );
}
private function Read($length = 1)  {
        $string = substr( $this->packet, 0, $length );
        $this->packet = substr( $this->packet, $length );
        return $string;
}
private function Read_String()   {
        $length = strpos( $this->packet, "\x00" );
        if( $length === FALSE )
          {
            $string = $this->packet;
            $this->packet = "";
          }
        else
          {
            $string = $this->Read(++$length );
            $string = trim($string, "\x00");

          }
        return $string;
       
}
}
function query_source($address){
    $array = explode(":", $address);
    $server = array();
    $server['status'] = 0;
    $server['ip']     = $array[0];
    $server['port']   = $array[1];
    $ip = $array[0];
    $port = $array[1];
    $info = new query();
    $info->SetServer($ip,$port);
    $info->Info();
    $server['name']        = $info->info['name'];
    $server['map']         = $info->info['map'];
    $server['game']        = $info->info['dir'];
    $server['description'] = $info->info['description'];
    $server['players']     = $info->info['players'];
    $server['playersmax']  = $info->info['max_players'];
    $server['bots']        = $info->info['bots'];
    $server['status']      = 1;
    $server['dedicated']   = $info->info['dedicated'];
    $server['os']          = $info->info['os'];
    $server['password']    = $info->info['password'];
    $server['vac']         = $info->info['secure'];
    return $server;
       
   
  }
  function lgsl_query_live($ip, $port){
   $return = array();
   $info = new query();
   $info->SetServer($ip,$port);
   $info->Info();
   $info->Rules();
   $info->Players();
   $return['b']['type']='source';
   $return['b']['ip']=$ip;
   $return['b']['c_port']=$port;
   $return['b']['c_port']=$port;
   $return['b']['q_port']=$port;
   $return['b']['status']=1;

   
   $return['s']['game']=$info->info['dir'];
   $return['s']['name']=$info->info['name'];
   $return['s']['map']=$info->info['map'];
   $return['s']['vac'] = $info->info['secure'];
   $return['e']['vac'] = $info->info['secure'];
   $return['s']['players']=$info->info['players'];
   $return['s']['playersmax']=$info->info['max_players'];
   $return['s']['password']=$info->info['password'];
   
   
   $return['e']=$info->rules;
   $return['e']['anticheat'] = $info->info['secure'];
   
   $i=0;
  foreach($info->players as $player)
  {
   $return['p'][$i]['pid'] = $i;
   $return['p'][$i]['name'] = $player['name'];
   $return['p'][$i]['score'] = $player['kills'];
   $return['p'][$i]['time'] = $player['time'];
   $i++;
  }
   return $return;
  }
?>
