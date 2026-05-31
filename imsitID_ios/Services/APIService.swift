//
//  APIService.swift
//  imsitID
//

import Foundation

class APIService: ObservableObject {
    static let shared = APIService()
    
    // Замените на ваш базовый URL
    private let baseURL = "https://imsit.shop"
    
    private init() {}
    
    // MARK: - Groups
    
    func fetchGroups() async throws -> [String] {
        guard let url = URL(string: "\(baseURL)/api/get_available_groups.php") else {
            throw APIError.invalidURL
        }
        
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(GroupsResponse.self, from: data)
        
        guard response.success else {
            throw APIError.serverError(response.error ?? "Unknown error")
        }
        
        return response.groups
    }
    
    // MARK: - Teachers
    
    func fetchTeachers() async throws -> [String] {
        guard let url = URL(string: "\(baseURL)/api/get_available_teachers.php") else {
            throw APIError.invalidURL
        }
        
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(TeachersResponse.self, from: data)
        
        guard response.success else {
            throw APIError.serverError(response.error ?? "Unknown error")
        }
        
        return response.teachers
    }
    
    // MARK: - Group Schedule
    
    func fetchGroupSchedule(group: String, week: Int, day: Int? = nil) async throws -> GroupScheduleResponse {
        var urlString = "\(baseURL)/api/get_group_schedule.php?group=\(group.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? "")&week=\(week)"
        if let day = day {
            urlString += "&day=\(day)"
        }
        
        guard let url = URL(string: urlString) else {
            throw APIError.invalidURL
        }
        
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(GroupScheduleResponse.self, from: data)
        
        guard response.success else {
            throw APIError.serverError(response.error ?? "Unknown error")
        }
        
        return response
    }
    
    // MARK: - Teacher Schedule
    
    func fetchTeacherSchedule(teacher: String, week: Int, day: Int? = nil) async throws -> TeacherScheduleResponse {
        var urlString = "\(baseURL)/api/get_teacher_schedule_ios.php?teacher=\(teacher.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? "")&week=\(week)"
        if let day = day {
            urlString += "&day=\(day)"
        }
        
        guard let url = URL(string: urlString) else {
            throw APIError.invalidURL
        }
        
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(TeacherScheduleResponse.self, from: data)
        
        guard response.success else {
            throw APIError.serverError(response.error ?? "Unknown error")
        }
        
        return response
    }
    
    // MARK: - Current Lesson
    
    func fetchCurrentLesson(group: String? = nil, teacher: String? = nil) async throws -> CurrentLessonResponse {
        var urlString: String
        if let group = group {
            urlString = "\(baseURL)/api/get_current_lesson.php?group=\(group.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? "")"
        } else if let teacher = teacher {
            urlString = "\(baseURL)/api/get_teacher_current_lesson.php?teacher_name=\(teacher.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? "")"
        } else {
            throw APIError.invalidURL
        }
        
        guard let url = URL(string: urlString) else {
            throw APIError.invalidURL
        }
        
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(CurrentLessonResponse.self, from: data)
        
        guard response.success else {
            throw APIError.serverError(response.error ?? "Unknown error")
        }
        
        return response
    }
    
    // MARK: - Full Schedule Load (for offline)
    
    func fetchFullSchedule(group: String? = nil, teacher: String? = nil) async throws -> Schedule {
        var schedule = Schedule()
        
        guard group != nil || teacher != nil else {
            throw APIError.invalidURL
        }
        
        for week in 1...2 {
            for day in 1...6 {
                let lessons: [Lesson]
                
                if let group = group {
                    let response = try await fetchGroupSchedule(group: group, week: week, day: day)
                    lessons = response.schedule
                } else if let teacher = teacher {
                    let response = try await fetchTeacherSchedule(teacher: teacher, week: week, day: day)
                    lessons = response.schedule
                } else {
                    continue
                }
                
                schedule.setLessons(week: week, day: day, lessons: lessons)
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
    let schedule: [Lesson]
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
}

struct TeacherScheduleResponse: Codable {
    let success: Bool
    let schedule: [Lesson]
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
        schedule = try container.decodeIfPresent([Lesson].self, forKey: .schedule) ?? []
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

