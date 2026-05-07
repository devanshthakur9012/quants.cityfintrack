import logging

logging.basicConfig(filemode='a', filename='dev.log', level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")
logger = logging.getLogger("--Kite-Login-request-token--")