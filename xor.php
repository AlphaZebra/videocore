<? php
function pz_xor( $string ) {
    // Let's define our key here
    $key = 'CarolynFayeRichardson';

    // Our plaintext/ciphertext
    $text = $string;

    // Our output text
    $outText = '';

    // Iterate through each character
    for($i=0; $i<strlen($text); )
    {
        for($j=0; $j<strlen($key); $j++,$i++)
        {
            $outText .= ($text[$i] ^ $key[$j]);
        }
    }
    return $outText;
 }