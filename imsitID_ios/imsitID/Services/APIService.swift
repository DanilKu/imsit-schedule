//
//  APIService.swift
//  imsitID
//

import Foundation

final class APIService: @unchecked Sendable {
    static let shared = APIService()
    
    // Замените на ваш базовый URL
    private let baseURL = "https://imsit.shop"
    
    private init() {}
    
    // MARK: - Groups
    
    func fetchGroups() async throws -> [String] {
        guard let url = URL(string: "\(baseURL)/api/get_available_groups.php") else {
            throw APIError.invalidURL
        }
        
        print("DEBUG: Fetching groups from: \(url.absoluteString)")
        
        let (data, response) = try await URLSession.shared.data(from: url)
        
        // Проверяем HTTP статус
        if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode != 200 {
            throw APIError.serverError("HTTP \(httpResponse.statusCode)")
        }
        
        // Проверяем, что это JSON
        if let responseString = String(data: data, encoding: .utf8),
           responseString.trimmingCharacters(in: .whitespacesAndNewlines).hasPrefix("<") {
            throw APIError.serverError("Server returned HTML instead of JSON")
        }
        
        let decodedResponse = try JSONDecoder().decode(GroupsResponse.self, from: data)
        
        guard decodedResponse.success else {
            throw APIError.serverError(decodedResponse.error ?? "Unknown error")
        }
        
        return decodedResponse.groups
    }
    
    // MARK: - Teachers
    
    func fetchTeachers() async throws -> [String] {
        guard let url = URL(string: "\(baseURL)/api/get_available_teachers.php") else {
            throw APIError.invalidURL
        }
        
        print("DEBUG: Fetching teachers from: \(url.absoluteString)")
        
        let (data, response) = try await URLSession.shared.data(from: url)
        
        // Проверяем HTTP статус
        if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode != 200 {
            throw APIError.serverError("HTTP \(httpResponse.statusCode)")
        }
        
        // Проверяем, что это JSON
        if let responseString = String(data: data, encoding: .utf8),
           responseString.trimmingCharacters(in: .whitespacesAndNewlines).hasPrefix("<") {
            throw APIError.serverError("Server returned HTML instead of JSON")
        }
        
        let decodedResponse = try JSONDecoder().decode(TeachersResponse.self, from: data)
        
        guard decodedResponse.success else {
            throw APIError.serverError(decodedResponse.error ?? "Unknown error")
        }
        
        return decodedResponse.teachers
    }
    
    // MARK: - Group Schedule
    
    func fetchGroupSchedule(group: String, week: Int, day: Int? = nil) async throws -> GroupScheduleResponse {
        // Используем URLComponents для правильной кодировки
        guard var urlComponents = URLComponents(string: "\(baseURL)/api/get_group_schedule.php") else {
            throw APIError.invalidURL
        }
        
        var queryItems: [URLQueryItem] = [
            URLQueryItem(name: "group", value: group),
            URLQueryItem(name: "week", value: String(week))
        ]
        
        if let day = day {
            queryItems.append(URLQueryItem(name: "day", value: String(day)))
        }
        
        // Используем стандартную кодировку URLComponents
        urlComponents.queryItems = queryItems
        
        guard let url = urlComponents.url else {
            print("DEBUG: Failed to create URL from components")
            throw APIError.invalidURL
        }
        
        print("DEBUG: Original group name: '\(group)'")
        print("DEBUG: Final URL: \(url.absoluteString)")
        
        let (data, response) = try await URLSession.shared.data(from: url)
        
        // Проверяем HTTP статус
        if let httpResponse = response as? HTTPURLResponse {
            print("DEBUG: HTTP Status Code: \(httpResponse.statusCode) for week=\(week), day=\(day ?? 0)")
            if httpResponse.statusCode != 200 {
                if let errorString = String(data: data, encoding: .utf8) {
                    print("DEBUG: Server error response: \(errorString.prefix(500))")
                }
                throw APIError.serverError("HTTP \(httpResponse.statusCode)")
            }
        }
        
        // Проверяем, что это JSON, а не HTML
        if let responseString = String(data: data, encoding: .utf8) {
            if responseString.trimmingCharacters(in: .whitespacesAndNewlines).hasPrefix("<") {
                print("DEBUG: Received HTML instead of JSON for week=\(week), day=\(day ?? 0)")
                print("DEBUG: Full URL was: \(url.absoluteString)")
                print("DEBUG: HTML response (first 1000 chars): \(responseString.prefix(1000))")
                throw APIError.serverError("Server returned HTML instead of JSON (404 page?)")
            }
            print("DEBUG: API Response (first 200 chars): \(responseString.prefix(200))")
        }
        
        let decodedResponse: GroupScheduleResponse
        do {
            decodedResponse = try JSONDecoder().decode(GroupScheduleResponse.self, from: data)
        } catch {
            if let responseString = String(data: data, encoding: .utf8) {
                print("DEBUG: JSON Decoding Error for week=\(week), day=\(day ?? 0): \(error)")
                print("DEBUG: Response content: \(responseString.prefix(500))")
            }
            throw APIError.decodingError
        }
        
        guard decodedResponse.success else {
            throw APIError.serverError(decodedResponse.error ?? "Unknown error")
        }
        
        print("DEBUG: Decoded schedule count: \(decodedResponse.schedule.count) for week=\(week), day=\(day ?? 0)")
        if !decodedResponse.schedule.isEmpty {
            print("DEBUG: First lesson: \(decodedResponse.schedule[0].subjectName)")
        }
        
        return decodedResponse
    }
    
    // MARK: - Teacher Schedule
    
    func fetchTeacherSchedule(teacher: String, week: Int, day: Int? = nil) async throws -> TeacherScheduleResponse {
        // Используем URLComponents для правильной кодировки
        guard var urlComponents = URLComponents(string: "\(baseURL)/api/get_teacher_schedule_ios.php") else {
            throw APIError.invalidURL
        }
        
        var queryItems: [URLQueryItem] = [
            URLQueryItem(name: "teacher", value: teacher),
            URLQueryItem(name: "week", value: String(week))
        ]
        
        if let day = day {
            queryItems.append(URLQueryItem(name: "day", value: String(day)))
        }
        
        // Используем стандартную кодировку URLComponents
        urlComponents.queryItems = queryItems
        
        guard let url = urlComponents.url else {
            print("DEBUG: Failed to create URL from components")
            throw APIError.invalidURL
        }
        
        print("DEBUG: Original teacher name: '\(teacher)'")
        print("DEBUG: Final URL: \(url.absoluteString)")
        
        let (data, response) = try await URLSession.shared.data(from: url)
        
        // Проверяем HTTP статус
        if let httpResponse = response as? HTTPURLResponse {
            print("DEBUG: HTTP Status Code: \(httpResponse.statusCode) for week=\(week), day=\(day ?? 0)")
            if httpResponse.statusCode != 200 {
                if let errorString = String(data: data, encoding: .utf8) {
                    print("DEBUG: Server error response: \(errorString.prefix(500))")
                }
                throw APIError.serverError("HTTP \(httpResponse.statusCode)")
            }
        }
        
        // Проверяем, что это JSON, а не HTML
        if let responseString = String(data: data, encoding: .utf8) {
            if responseString.trimmingCharacters(in: .whitespacesAndNewlines).hasPrefix("<") {
                print("DEBUG: Received HTML instead of JSON for week=\(week), day=\(day ?? 0)")
                print("DEBUG: Full URL was: \(url.absoluteString)")
                print("DEBUG: HTML response (first 1000 chars): \(responseString.prefix(1000))")
                throw APIError.serverError("Server returned HTML instead of JSON (404 page?)")
            }
            print("DEBUG: API Response (first 200 chars): \(responseString.prefix(200))")
        }
        
        let decodedResponse: TeacherScheduleResponse
        do {
            decodedResponse = try JSONDecoder().decode(TeacherScheduleResponse.self, from: data)
        } catch {
            if let responseString = String(data: data, encoding: .utf8) {
                print("DEBUG: JSON Decoding Error for week=\(week), day=\(day ?? 0): \(error)")
                print("DEBUG: Response content: \(responseString.prefix(500))")
            }
            throw APIError.decodingError
        }
        
        guard decodedResponse.success else {
            throw APIError.serverError(decodedResponse.error ?? "Unknown error")
        }
        
        return decodedResponse
    }
    
    // MARK: - Current Lesson
    
    func fetchCurrentLesson(group: String? = nil, teacher: String? = nil) async throws -> CurrentLessonResponse {
        let endpoint: String
        let paramName: String
        let paramValue: String
        
        if let group = group {
            endpoint = "\(baseURL)/api/get_current_lesson.php"
            paramName = "group"
            paramValue = group
        } else if let teacher = teacher {
            endpoint = "\(baseURL)/api/get_teacher_current_lesson.php"
            paramName = "teacher_name"
            paramValue = teacher
        } else {
            throw APIError.invalidURL
        }
        
        guard var urlComponents = URLComponents(string: endpoint) else {
            throw APIError.invalidURL
        }
        
        urlComponents.queryItems = [
            URLQueryItem(name: paramName, value: paramValue)
        ]
        
        guard let url = urlComponents.url else {
            print("DEBUG: Failed to create URL from components")
            throw APIError.invalidURL
        }
        
        print("DEBUG: Fetching current lesson URL: \(url.absoluteString)")
        
        let (data, response) = try await URLSession.shared.data(from: url)
        
        // Проверяем HTTP статус
        if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode != 200 {
            if let errorString = String(data: data, encoding: .utf8) {
                print("DEBUG: Current lesson error response: \(errorString.prefix(500))")
            }
            throw APIError.serverError("HTTP \(httpResponse.statusCode)")
        }
        
        // Проверяем, что это JSON
        if let responseString = String(data: data, encoding: .utf8) {
            if responseString.trimmingCharacters(in: .whitespacesAndNewlines).hasPrefix("<") {
                print("DEBUG: Received HTML instead of JSON for current lesson")
                throw APIError.serverError("Server returned HTML instead of JSON")
            }
            print("DEBUG: Current lesson API response: \(responseString.prefix(500))")
        }
        
        let decodedResponse: CurrentLessonResponse
        do {
            decodedResponse = try JSONDecoder().decode(CurrentLessonResponse.self, from: data)
        } catch {
            if let responseString = String(data: data, encoding: .utf8) {
                print("DEBUG: JSON Decoding Error for current lesson: \(error)")
                print("DEBUG: Response content: \(responseString.prefix(500))")
            }
            throw APIError.decodingError
        }
        
        guard decodedResponse.success else {
            throw APIError.serverError(decodedResponse.error ?? "Unknown error")
        }
        
        print("DEBUG: Decoded current lesson - current: \(decodedResponse.currentLesson != nil), next: \(decodedResponse.nextLesson != nil)")
        
        return decodedResponse
    }
    
    // MARK: - Full Schedule Load (for offline)
    
    func fetchFullSchedule(group: String? = nil, teacher: String? = nil) async throws -> Schedule {
        var schedule = Schedule()
        
        guard group != nil || teacher != nil else {
            throw APIError.invalidURL
        }
        
        for week in 1...2 {
            for day in 1...6 {
                do {
                    var fetchedLessons: [Lesson] = []
                    
                    if let group = group {
                        let response = try await fetchGroupSchedule(group: group, week: week, day: day)
                        fetchedLessons = response.schedule
                        print("DEBUG: Fetched group schedule - week=\(week), day=\(day), lessons count=\(fetchedLessons.count)")
                        if !fetchedLessons.isEmpty {
                            print("DEBUG: First lesson subject: \(fetchedLessons[0].subjectName), dayOfWeek: \(fetchedLessons[0].dayOfWeek)")
                        }
                    } else if let teacher = teacher {
                        let response = try await fetchTeacherSchedule(teacher: teacher, week: week, day: day)
                        fetchedLessons = response.schedule
                        print("DEBUG: Fetched teacher schedule - week=\(week), day=\(day), lessons count=\(fetchedLessons.count)")
                        if !fetchedLessons.isEmpty {
                            print("DEBUG: First lesson subject: \(fetchedLessons[0].subjectName), dayOfWeek: \(fetchedLessons[0].dayOfWeek)")
                        }
                    } else {
                        continue
                    }
                    
                    // Убеждаемся, что dayOfWeek соответствует дню запроса
                    for i in 0..<fetchedLessons.count {
                        fetchedLessons[i].dayOfWeek = day
                        fetchedLessons[i].weekNumber = week
                    }
                    
                    schedule.setLessons(week: week, day: day, lessons: fetchedLessons)
                    print("DEBUG: Set lessons in schedule - week=\(week), day=\(day), count=\(fetchedLessons.count)")
                } catch {
                    print("DEBUG: Error fetching schedule for week=\(week), day=\(day): \(error)")
                    // Продолжаем загрузку других дней даже при ошибке
                }
            }
        }
        
        return schedule
    }
}

// MARK: - Response Models

struct GroupsResponse: Codable {
    let success: Bool
    let groups: [String]
    let error: String?
}

struct TeachersResponse: Codable {
    let success: Bool
    let teachers: [String]
    let error: String?
}

struct GroupScheduleResponse: Codable {
    let success: Bool
    var schedule: [Lesson]
    let week: Int?
    let day: Int?
    let currentLesson: Lesson?
    let nextLesson: Lesson?
    let error: String?
    
    enum CodingKeys: String, CodingKey {
        case success, schedule, week, day, error
        case currentLesson = "current_lesson"
        case nextLesson = "next_lesson"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        success = try container.decode(Bool.self, forKey: .success)
        
        // Пытаемся декодировать schedule как массив
        if let scheduleArray = try? container.decode([Lesson].self, forKey: .schedule) {
            schedule = scheduleArray
        } else {
            // Если не получилось, пробуем как пустой массив
            schedule = []
        }
        
        week = try container.decodeIfPresent(Int.self, forKey: .week)
        day = try container.decodeIfPresent(Int.self, forKey: .day)
        currentLesson = try container.decodeIfPresent(Lesson.self, forKey: .currentLesson)
        nextLesson = try container.decodeIfPresent(Lesson.self, forKey: .nextLesson)
        error = try container.decodeIfPresent(String.self, forKey: .error)
    }
}

struct TeacherScheduleResponse: Codable {
    let success: Bool
    var schedule: [Lesson]
    let week: [String: [Lesson]]?
    let weekNumber: Int?
    let day: Int?
    let currentLesson: Lesson?
    let nextLesson: Lesson?
    let error: String?
    
    enum CodingKeys: String, CodingKey {
        case success, schedule, week, day, error
        case weekNumber = "currentWeek"
        case currentLesson = "current_lesson"
        case nextLesson = "next_lesson"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        success = try container.decode(Bool.self, forKey: .success)
        
        // Пытаемся декодировать schedule как массив
        if let scheduleArray = try? container.decode([Lesson].self, forKey: .schedule) {
            schedule = scheduleArray
        } else {
            schedule = []
        }
        
        week = try container.decodeIfPresent([String: [Lesson]].self, forKey: .week)
        weekNumber = try container.decodeIfPresent(Int.self, forKey: .weekNumber)
        day = try container.decodeIfPresent(Int.self, forKey: .day)
        currentLesson = try container.decodeIfPresent(Lesson.self, forKey: .currentLesson)
        nextLesson = try container.decodeIfPresent(Lesson.self, forKey: .nextLesson)
        error = try container.decodeIfPresent(String.self, forKey: .error)
    }
}

struct CurrentLessonResponse: Codable {
    let success: Bool
    let currentLesson: Lesson?
    let nextLesson: Lesson?
    let progress: Double?
    let error: String?
    
    enum CodingKeys: String, CodingKey {
        case success, progress, error
        case currentLesson = "currentLesson"
        case nextLesson = "nextLesson"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        success = try container.decode(Bool.self, forKey: .success)
        currentLesson = try container.decodeIfPresent(Lesson.self, forKey: .currentLesson)
        nextLesson = try container.decodeIfPresent(Lesson.self, forKey: .nextLesson)
        progress = try container.decodeIfPresent(Double.self, forKey: .progress)
        error = try container.decodeIfPresent(String.self, forKey: .error)
    }
}

enum APIError: LocalizedError {
    case invalidURL
    case serverError(String)
    case decodingError
    case networkError
    
    var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "Неверный URL"
        case .serverError(let message):
            return message
        case .decodingError:
            return "Ошибка декодирования данных"
        case .networkError:
            return "Ошибка сети"
        }
    }
}

