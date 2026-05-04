import Foundation

/// Minimal JSON value used for auth payloads extended by Pair projects.
public enum PairJSONValue: Codable, Equatable, Sendable {
	case string(String)
	case int(Int)
	case double(Double)
	case bool(Bool)
	case array([PairJSONValue])
	case object([String: PairJSONValue])
	case null

	/// Creates a JSON value from a string.
	public init(_ value: String) {
		self = .string(value)
	}

	/// Creates a JSON value from an integer.
	public init(_ value: Int) {
		self = .int(value)
	}

	/// Creates a JSON value from a decimal number.
	public init(_ value: Double) {
		self = .double(value)
	}

	/// Creates a JSON value from a boolean.
	public init(_ value: Bool) {
		self = .bool(value)
	}

	/// Decodes a primitive JSON value.
	public init(from decoder: Decoder) throws {
		let container = try decoder.singleValueContainer()

		if container.decodeNil() {
			self = .null
		} else if let value = try? container.decode(Bool.self) {
			self = .bool(value)
		} else if let value = try? container.decode(Int.self) {
			self = .int(value)
		} else if let value = try? container.decode(Double.self) {
			self = .double(value)
		} else if let value = try? container.decode([PairJSONValue].self) {
			self = .array(value)
		} else if let value = try? container.decode([String: PairJSONValue].self) {
			self = .object(value)
		} else {
			self = .string(try container.decode(String.self))
		}
	}

	/// Encodes the primitive JSON value.
	public func encode(to encoder: Encoder) throws {
		var container = encoder.singleValueContainer()

		switch self {
		case .string(let value):
			try container.encode(value)
		case .int(let value):
			try container.encode(value)
		case .double(let value):
			try container.encode(value)
		case .bool(let value):
			try container.encode(value)
		case .array(let value):
			try container.encode(value)
		case .object(let value):
			try container.encode(value)
		case .null:
			try container.encodeNil()
		}
	}
}

extension PairJSONValue: ExpressibleByStringLiteral {

	/// Creates a JSON string value from a Swift literal.
	public init(stringLiteral value: String) {
		self = .string(value)
	}
}

extension PairJSONValue: ExpressibleByIntegerLiteral {

	/// Creates a JSON integer value from a Swift literal.
	public init(integerLiteral value: Int) {
		self = .int(value)
	}
}

extension PairJSONValue: ExpressibleByFloatLiteral {

	/// Creates a JSON decimal value from a Swift literal.
	public init(floatLiteral value: Double) {
		self = .double(value)
	}
}

extension PairJSONValue: ExpressibleByBooleanLiteral {

	/// Creates a JSON boolean value from a Swift literal.
	public init(booleanLiteral value: Bool) {
		self = .bool(value)
	}
}

extension PairJSONValue: ExpressibleByArrayLiteral {

	/// Creates a JSON array from a Swift literal.
	public init(arrayLiteral elements: PairJSONValue...) {
		self = .array(elements)
	}
}

extension PairJSONValue: ExpressibleByDictionaryLiteral {

	/// Creates a JSON object from a Swift literal.
	public init(dictionaryLiteral elements: (String, PairJSONValue)...) {
		self = .object(Dictionary(uniqueKeysWithValues: elements))
	}
}
