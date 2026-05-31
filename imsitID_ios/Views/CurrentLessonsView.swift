//
//  CurrentLessonsView.swift
//  imsitID
//

import SwiftUI

struct CurrentLessonsView: View {
    @EnvironmentObject var appState: AppState
    
    var body: some View {
        VStack(spacing: 12) {
            if let currentLesson = appState.currentLesson {
                CurrentLessonCard(lesson: currentLesson)
            }
            
            if let nextLesson = appState.nextLesson {
                NextLessonCard(lesson: nextLesson)
            }
        }
    }
}

struct CurrentLessonCard: View {
    let lesson: Lesson
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Label("Сейчас", systemImage: "circle.fill")
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(Color(red: 0.063, green: 0.725, blue: 0.506))
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(
                        Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.2)
                    )
                    .cornerRadius(12)
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.3), lineWidth: 1)
                    )
                
                Spacer()
                
                Text(lesson.timeRange)
                    .font(.system(size: 13))
                    .foregroundColor(.white.opacity(0.7))
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(.white)
                .lineLimit(2)
            
            HStack(spacing: 8) {
                Label(lesson.roomNumber, systemImage: "door.left.hand.open")
                    .font(.system(size: 13))
                    .foregroundColor(.white.opacity(0.7))
                
                Text("•")
                    .foregroundColor(.white.opacity(0.5))
                
                if let groups = lesson.groups, !groups.isEmpty {
                    Label(groups.joined(separator: ", "), systemImage: "person.3.fill")
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                } else {
                    Label(lesson.teacherName, systemImage: "person.fill")
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                }
            }
            
            VStack(spacing: 6) {
                GeometryReader { geometry in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: 6)
                            .fill(Color.white.opacity(0.1))
                            .frame(height: 6)
                        
                        RoundedRectangle(cornerRadius: 6)
                            .fill(
                                LinearGradient(
                                    colors: [
                                        Color(red: 0.063, green: 0.725, blue: 0.506),
                                        Color(red: 0.022, green: 0.588, blue: 0.412)
                                    ],
                                    startPoint: .leading,
                                    endPoint: .trailing
                                )
                            )
                            .frame(width: geometry.size.width * lesson.progress, height: 6)
                    }
                }
                .frame(height: 6)
                
                HStack {
                    Label("до конца пары: \(lesson.remainingTime)", systemImage: "hourglass")
                        .font(.system(size: 12))
                        .foregroundColor(.white.opacity(0.6))
                }
            }
        }
        .padding(16)
        .glassEffect(cornerRadius: 16)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.3),
                            Color(red: 0.063, green: 0.725, blue: 0.506).opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1
                )
        )
    }
}

struct NextLessonCard: View {
    let lesson: Lesson
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Label("Следующая", systemImage: "arrow.right")
                    .font(.system(size: 13, weight: .medium))
                    .foregroundColor(Color(red: 0.231, green: 0.510, blue: 0.965))
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(
                        Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2)
                    )
                    .cornerRadius(12)
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.3), lineWidth: 1)
                    )
                
                Spacer()
                
                Text(lesson.timeRange)
                    .font(.system(size: 13))
                    .foregroundColor(.white.opacity(0.7))
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(.white)
                .lineLimit(2)
            
            HStack(spacing: 8) {
                Label(lesson.roomNumber, systemImage: "door.left.hand.open")
                    .font(.system(size: 13))
                    .foregroundColor(.white.opacity(0.7))
                
                Text("•")
                    .foregroundColor(.white.opacity(0.5))
                
                if let groups = lesson.groups, !groups.isEmpty {
                    Label(groups.joined(separator: ", "), systemImage: "person.3.fill")
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                } else {
                    Label(lesson.teacherName, systemImage: "person.fill")
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                }
            }
        }
        .padding(16)
        .glassEffect(cornerRadius: 16)
        .overlay(
            RoundedRectangle(cornerRadius: 16)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.3),
                            Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1
                )
        )
    }
}

