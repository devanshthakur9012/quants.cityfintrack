# from print import print
import requests, json, re, pyotp, asyncio, argparse
from urllib.parse import urlparse, parse_qs
from db.connection import save_req_token, fetch_user_details
from logger import logger


class API:
    def __init__(self, api_details):
        logger.info('kite api initialized')
        self.api_details = api_details
        self.login()

    def login(self):
        apikey = self.api_details["api_key"]
        secretkey = self.api_details["api_secret"]
        username = self.api_details["user_name"]
        password = self.api_details["password"]
        # security_pin not used currently
        # security_pin = self.api_details["security_pin"] 
        totp_base_secret = self.api_details["totp_secret"]
        
        try:
            # for NTK account
            login_url = "https://kite.trade/connect/login?api_key=" + str(apikey)
            
            # for XXQ account v3 
            login_url = "https://kite.trade/connect/login?v=3&api_key=" + str(apikey)
            
            request_token = ""
            session = requests.Session()
            session.headers.update({'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3'})

            # session.get(login_url)
            res0 = session.get(login_url)
            # print.info(f"RES 0 = {res0.url}")
            res1 = session.post("https://kite.zerodha.com/api/login",
                                data={'user_id': username,
                                      'password': password})
            
            data = json.loads(res1.text)
            logger.info(f"{data=}")


            # authenticator_totp = str(otp.get_totp(totp_base_secret))
            authenticator_totp = pyotp.TOTP(totp_base_secret).now()
            logger.info(f"{authenticator_totp=}")
            res2 = session.post("https://kite.zerodha.com/api/twofa",
                                data={'user_id': username,
                                      'request_id': data['data']["request_id"],
                                      'twofa_value': authenticator_totp})
            
            # print.debug(f"{res0.url} &skip_session=true")
            
            try:
                # print.info(f"RES 0 URL = {res0.url}")

                res = session.get(res0.url + "&skip_session=true")

                parsed = urlparse(res.history[1].headers['location'])
                request_token = parse_qs(parsed.query)['request_token'][0]
                header_string = res2.headers['Set-Cookie']
                pattern = r"enctoken=(?P<token>.+?)\;"
                self.enctoken = re.search(pattern, header_string).groupdict()['token']
            
            except Exception as e:
                logger.error(f"Error in getting request token for {username}, {e.__str__()}", exc_info=True)

            session.close()

            logger.info(f"request token = {request_token}")
            print(f"requesttoken={request_token}")

            save_req_token(self.api_details['user_name'], request_token)

            # kite = KiteConnect(api_key=apikey)
            # print(f"{request_token=}")
            # data = kite.generate_session(request_token, api_secret=secretkey)
            # self.data = data

            # app = KiteConnect(apikey)
            # app.set_access_token(data['access_token'])

            # self.broker = app

        except Exception as e:
            self.broker = None
            save_req_token(self.api_details['user_name'], "")
            logger.critical(f"Error in logging in for {username}, {e.__str__()}", exc_info=True)

async def main():
    """
    ## Procedure
    ### (check dev.log file, if you want to check the output status)

    #### Get the api details dict
    api_details = {
        "api_key": "99n9vrxlgyxklpht",
        "api_secret": "adjl97sewgv1utfycl3ens7ks545hpcr",
        "user_name": "BFF348",
        "password": "venue@123",
        "security_pin": "243569",
        "totp_secret": "4AMQ5W5EHKIRZ33Z6EVI7W4HUS3KKDB2"
    }

    #### Pass it to API class which will perform login method and save token to MYSQL-DB
    u = API(api_details)
    """
    
    # create parser
    parser = argparse.ArgumentParser(description='This Script gets the request token and saves it in DB')

    # Add optional arguments
    parser.add_argument('--user', '-u', help='Username on broker, eg; BFF348')
    args = parser.parse_args()
    
    if args.user is not None:
        username = args.user
        user_data = fetch_user_details(username)
        if user_data is not None and len(user_data) != 0:
            logger.info(f"Username parameter: {username}")
            logger.info(f"User data: {user_data}")

            api_details = {
                "api_key": user_data[6],
                "api_secret": user_data[7],
                "user_name": user_data[4],
                "password": user_data[5],
                "security_pin": user_data[8],
                "totp_secret": user_data[9]
            }
            u = API(api_details)

        else:
            logger.error(f"No user Found: {username}")
    else:
        logger.warning(f"Username parameter empty: {args.user}")


if __name__ == '__main__' :
    asyncio.run(main())
    