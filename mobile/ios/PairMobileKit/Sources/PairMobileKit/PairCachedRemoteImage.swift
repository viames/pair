import Foundation
import SwiftUI

#if canImport(UIKit)
import UIKit
fileprivate typealias PairPlatformImage = UIImage
#elseif canImport(AppKit)
import AppKit
fileprivate typealias PairPlatformImage = NSImage
#endif

/// Remote image cache with fast memory storage, disk URLCache, and cookie-free requests.
@MainActor
public enum PairRemoteImageCache {
	private static let memoryCache = NSCache<NSString, PairPlatformImage>()
	private static let diskCache = URLCache(
		memoryCapacity: 24 * 1024 * 1024,
		diskCapacity: 180 * 1024 * 1024,
		diskPath: "pair.mobile.remote-images"
	)
	private static let session: URLSession = {
		let configuration = URLSessionConfiguration.default
		configuration.urlCache = diskCache
		configuration.requestCachePolicy = .returnCacheDataElseLoad
		configuration.timeoutIntervalForRequest = 30
		configuration.timeoutIntervalForResource = 90
		configuration.httpCookieAcceptPolicy = .never
		configuration.httpCookieStorage = nil
		configuration.httpShouldSetCookies = false

		return URLSession(configuration: configuration)
	}()

	/// Clears memory and disk caches when account or tenant changes.
	public static func clear() {
		memoryCache.removeAllObjects()
		diskCache.removeAllCachedResponses()
	}

	/// Returns a previously downloaded image from memory cache.
	fileprivate static func image(for url: URL) -> PairPlatformImage? {
		memoryCache.object(forKey: cacheKey(for: url))
	}

	/// Downloads the image through URLCache and then keeps it in memory.
	fileprivate static func downloadImage(from url: URL) async throws -> PairPlatformImage {
		if let cachedImage = image(for: url) {
			return cachedImage
		}

		var request = URLRequest(url: url, cachePolicy: .returnCacheDataElseLoad)
		request.setValue("image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8", forHTTPHeaderField: "Accept")

		let (data, response) = try await session.data(for: request)

		guard let httpResponse = response as? HTTPURLResponse,
		      (200...299).contains(httpResponse.statusCode),
		      let image = PairPlatformImage(data: data) else {
			throw URLError(.cannotDecodeContentData)
		}

		memoryCache.setObject(image, forKey: cacheKey(for: url))

		return image
	}

	/// Builds a stable key from the absolute URL.
	private static func cacheKey(for url: URL) -> NSString {
		url.absoluteString as NSString
	}
}

/// AsyncImage alternative that reuses the package-level Pair cache.
public struct PairCachedRemoteImage<Content: View, Placeholder: View>: View {
	private let url: URL?
	private let content: (Image) -> Content
	private let placeholder: () -> Placeholder

	@State private var image: PairPlatformImage?

	private var loadIdentifier: String {
		url?.absoluteString ?? ""
	}

	/// Initializes the loader with an optional URL, content, and placeholder.
	public init(
		url: URL?,
		@ViewBuilder content: @escaping (Image) -> Content,
		@ViewBuilder placeholder: @escaping () -> Placeholder
	) {
		self.url = url
		self.content = content
		self.placeholder = placeholder
	}

	public var body: some View {
		Group {
			if let image {
				content(Image(pairPlatformImage: image))
			} else {
				placeholder()
			}
		}
		.task(id: loadIdentifier) {
			await loadImageIfNeeded()
		}
	}

	/// Loads the image only when it is not already present in the shared cache.
	@MainActor
	private func loadImageIfNeeded() async {
		guard let url else {
			image = nil
			return
		}

		if let cachedImage = PairRemoteImageCache.image(for: url) {
			image = cachedImage
			return
		}

		image = nil

		do {
			let downloadedImage = try await PairRemoteImageCache.downloadImage(from: url)

			if !Task.isCancelled {
				image = downloadedImage
			}
		} catch {
			if !Task.isCancelled {
				image = nil
			}
		}
	}
}

private extension Image {

	/// Creates a SwiftUI image from the current platform's native image type.
	init(pairPlatformImage image: PairPlatformImage) {
		#if canImport(UIKit)
		self.init(uiImage: image)
		#elseif canImport(AppKit)
		self.init(nsImage: image)
		#endif
	}
}
