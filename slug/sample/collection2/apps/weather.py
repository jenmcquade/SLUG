import requests
import sys
import json
import os

from pprint import pprint
cmdargs=sys.argv
APPID=os.environ['WEATHER_APP_API_KEY']

def weather_data(query):
  res=requests.get('http://api.openweathermap.org/data/2.5/weather?'+query+'&APPID='+APPID+'&units=imperial');
  return res.json();
def print_weather(result,city):
  print("{}'s temperature: {}°C ".format(city,result['main']['temp']))
  print("Wind speed: {} m/s".format(result['wind']['speed']))
  print("Description: {}".format(result['weather'][0]['description']))
  print("Weather: {}".format(result['weather'][0]['main']))
def main():
  city=cmdargs[1]
  try:
    query='q='+city;
    w_data=weather_data(query);
    print(json.dumps(w_data));
	  #print_weather(w_data, city)
	  #print()
  except:
    print('City name not found...')
if __name__=='__main__':
  main()