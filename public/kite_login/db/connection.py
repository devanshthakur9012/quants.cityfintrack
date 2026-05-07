"""
"""
import os, datetime, json
import pymysql as myc
from logger import logger

def connect_db():

    try: 
        DB_USERNAME="cityprofit"
        DB_PASSWORD="ei3fqxkhtxzlknq6l1zq"
        DB_HOST="139.84.169.226"
        DB_PORT=3306
        DB_DATABASE="cityprofitedge_bd"

        username = DB_USERNAME
        password = DB_PASSWORD
        host = DB_HOST
        port = int(DB_PORT)
        db_name = DB_DATABASE
        # connection_string = f"mysql://{username}:{password}@{host}:{port}/{db_name}?ssl-mode={ssl}"
        
        # Creating a connection using the connection string
        myc_conn = myc.connect(
            host=host,
            user=username,
            password=password,
            database=db_name,
            port=port
            # ssl_ca=ca_certificate_path,  # Specify the path to your CA certificate if ssl is required
            # ssl_verify_cert=True  
            # Enable or disable SSL certificate verification
        )

    except Exception as e:
        myc_conn = None
        logger.error(f"{e.__str__()}", exc_info=True)

    return myc_conn


def save_req_token(username, token):

    # table_name = "test"
    table_name = "broker_apis"
    token_field_update = "request_token"
    username_field_query = "account_user_name"

    conn = connect_db()
    cur = conn.cursor()

    query = f"""UPDATE `{table_name}` SET `{token_field_update}`=%s WHERE `{username_field_query}`=%s"""
    parameters = (token, username)
    cur.execute(query, parameters)
    conn.commit()

    cur.close()
    conn.close()

    logger.info(f"Token updated for: {username}")

def fetch_user_details(username):

    # table_name = "test"
    table_name = "broker_apis"
    username_field_query = "account_user_name"

    conn = connect_db()
    cur = conn.cursor()

    query = f"""SELECT * FROM `{table_name}` WHERE `{username_field_query}`=%s"""
    parameters = (username,)
    cur.execute(query, parameters)
    user_present = cur.fetchone()
    cur.close()
    conn.close()

    return user_present