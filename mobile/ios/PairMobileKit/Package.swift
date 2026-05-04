// swift-tools-version: 6.0

import PackageDescription

let package = Package(
	name: "PairMobileKit",
	platforms: [
		.iOS(.v17),
		.macOS(.v14),
	],
	products: [
		.library(
			name: "PairMobileKit",
			targets: ["PairMobileKit"]
		),
	],
	targets: [
		.target(name: "PairMobileKit"),
		.testTarget(
			name: "PairMobileKitTests",
			dependencies: ["PairMobileKit"]
		),
	]
)
