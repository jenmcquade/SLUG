#!bin/bash
# A cute little proxy of the Star Wars API
#
warn=''
s_type=$1
s_keyword=$2
re='^[0-9]+$'

if [ -z $1 ]; then
    s_type="people"
    warn=$(echo -e $warn',{"warning":"arg1 was missing, so default \"people\" was used."}')
fi

if [ -z $2 ]; then  
    s_keyword="r2"
    warn=$(echo -e $warn',{"warning":"arg2 was missing, so default \"R2\" was used."}')
fi

if ! [[ "$2" =~ ^[0-9]+$ ]]; then
    output="["$(curl https://swapi.dev/api/$s_type/?search=$s_keyword)
else
    output="["$(curl https://swapi.dev/api/$s_type/$s_keyword/)
fi

output=$output$warn"]"

echo $output
