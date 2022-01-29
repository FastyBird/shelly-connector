#!/usr/bin/python3

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
Shelly connector publishers module publisher for API v1
"""

# Python base dependencies
import time

# Library dependencies
from fastybird_metadata.devices_module import ConnectionState
from kink import inject

# Library libs
from fastybird_shelly_connector.api.transformers import DataTransformHelpers
from fastybird_shelly_connector.clients.client import Client
from fastybird_shelly_connector.publishers.publisher import IPublisher
from fastybird_shelly_connector.registry.model import (
    AttributesRegistry,
    BlocksRegistry,
    SensorsRegistry,
)
from fastybird_shelly_connector.registry.records import DeviceRecord
from fastybird_shelly_connector.types import DeviceAttribute


@inject(alias=IPublisher)
class Gen1Publisher(IPublisher):  # pylint: disable=too-few-public-methods
    """
    Data publisher for Gen1 devices

    @package        FastyBird:ShellyConnector!
    @module         publishers

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __attributes_registry: AttributesRegistry
    __blocks_registry: BlocksRegistry
    __sensors_registry: SensorsRegistry

    __client: Client

    __DEVICE_COMMUNICATION_TIMEOUT: float = 120.0

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        attributes_registry: AttributesRegistry,
        blocks_registry: BlocksRegistry,
        sensors_registry: SensorsRegistry,
        client: Client,
    ) -> None:
        self.__attributes_registry = attributes_registry
        self.__blocks_registry = blocks_registry
        self.__sensors_registry = sensors_registry

        self.__client = client

    # -----------------------------------------------------------------------------

    def handle(self, device: DeviceRecord) -> bool:  # pylint: disable=too-many-return-statements
        """Handle publish read or write message to device"""
        state_attribute_record = self.__attributes_registry.get_by_attribute(
            device_id=device.id,
            attribute_type=DeviceAttribute.STATE,
        )

        if (
            device.last_communication_timestamp is None
            or time.time() - device.last_communication_timestamp > self.__DEVICE_COMMUNICATION_TIMEOUT
        ):
            if state_attribute_record is not None and state_attribute_record.value != ConnectionState.LOST.value:
                self.__attributes_registry.set_value(
                    attribute=state_attribute_record,
                    value=ConnectionState.LOST.value,
                )

            return True

        if (
            device.last_communication_timestamp is not None
            and time.time() - device.last_communication_timestamp <= self.__DEVICE_COMMUNICATION_TIMEOUT
        ):
            if state_attribute_record is not None and state_attribute_record.value != ConnectionState.CONNECTED.value:
                self.__attributes_registry.set_value(
                    attribute=state_attribute_record,
                    value=ConnectionState.CONNECTED.value,
                )

        for block in self.__blocks_registry.get_all_by_device(device_id=device.id):
            for sensor in self.__sensors_registry.get_all_for_block(block_id=block.id):
                if sensor.expected_value != sensor.actual_value and (
                    sensor.expected_pending is None or time.time() - sensor.expected_pending >= 5
                ):
                    self.__client.write_sensor(
                        device_record=device,
                        block_record=block,
                        sensor_record=sensor,
                        write_value=DataTransformHelpers.transform_to_device(
                            data_type=sensor.data_type,
                            value_format=sensor.format,
                            value=sensor.expected_value,
                        ),
                    )

                    self.__sensors_registry.set_expected_pending(sensor=sensor, timestamp=time.time())

        return True
