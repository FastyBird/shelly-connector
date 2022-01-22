#     Copyright 2021. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

"""
Shelly connector plugin logger
"""

# Python base dependencies
import logging
from typing import Dict

# Library libs
from fb_shelly_connector.entities import ShellyConnectorEntity
from fb_shelly_connector.types import CONNECTOR_NAME


class Logger:
    """
    Plugin logger

    @package        FastyBird:ShellyConnector!
    @module         logger

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __connector: ShellyConnectorEntity

    __logger: logging.Logger

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        connector: ShellyConnectorEntity,
        logger: logging.Logger = logging.getLogger("dummy"),
    ) -> None:
        self.__connector = connector

        self.__logger = logger

    # -----------------------------------------------------------------------------

    def set_logger(self, logger: logging.Logger) -> None:
        """Configure custom logger handler"""
        self.__logger = logger

    # -----------------------------------------------------------------------------

    def debug(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log debugging message"""
        if "context" in kwargs:
            kwargs["context"] = {**kwargs["context"], **self.__get_connector_context()}
        else:
            kwargs["context"] = self.__get_connector_context()

        self.__logger.debug(msg, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def info(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log information message"""
        if "context" in kwargs:
            kwargs["context"] = {**kwargs["context"], **self.__get_connector_context()}
        else:
            kwargs["context"] = self.__get_connector_context()

        self.__logger.info(msg, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def warning(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log warning message"""
        if "context" in kwargs:
            kwargs["context"] = {**kwargs["context"], **self.__get_connector_context()}
        else:
            kwargs["context"] = self.__get_connector_context()

        self.__logger.warning(msg, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def error(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log error message"""
        if "context" in kwargs:
            kwargs["context"] = {**kwargs["context"], **self.__get_connector_context()}
        else:
            kwargs["context"] = self.__get_connector_context()

        self.__logger.error(msg, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def exception(self, msg: Exception) -> None:
        """Log thrown exception"""
        self.__logger.exception(msg)

    # -----------------------------------------------------------------------------

    def __get_connector_context(self) -> Dict:
        return {
            "connector": {
                "type": CONNECTOR_NAME,
                "id": self.__connector.id.__str__(),
            },
        }
